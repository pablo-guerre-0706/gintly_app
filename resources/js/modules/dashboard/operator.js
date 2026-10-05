import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { setSectionState } from '@/dashboard/section-state';
import { authorizedNavigation, flattenedNavigation } from '@/shell/navigation';

const PROFILE_META = Object.freeze({
    cajero: Object.freeze({
        label: 'Cajero',
        icon: 'fa-cash-register',
        metrics: Object.freeze([
            Object.freeze({ key: 'sesion_caja_abierta', label: 'Sesión de caja', nullable: true, format: (value) => value === null ? 'Sin sesión abierta' : `Sesión #${value}` }),
            Object.freeze({ key: 'cxc_cobrables', label: 'CxC cobrables', format: formatCount }),
        ]),
    }),
    facturador: Object.freeze({
        label: 'Facturador',
        icon: 'fa-file-invoice-dollar',
        metrics: Object.freeze([
            Object.freeze({ key: 'ventas_abiertas', label: 'Ventas abiertas', format: formatCount }),
        ]),
    }),
    bodeguero: Object.freeze({
        label: 'Bodeguero',
        icon: 'fa-boxes-stacked',
        metrics: Object.freeze([
            Object.freeze({ key: 'conteos_abiertos', label: 'Conteos abiertos', format: formatCount }),
            Object.freeze({ key: 'recepciones_en_discrepancia', label: 'Recepciones con discrepancia', format: formatCount }),
        ]),
    }),
    despachador: Object.freeze({
        label: 'Despachador',
        icon: 'fa-truck-fast',
        metrics: Object.freeze([
            Object.freeze({ key: 'despachos_de_sucursal', label: 'Despachos de la sucursal', format: formatCount }),
        ]),
    }),
});

const ACTION_KEYS = new Set([
    'operativeCash', 'operativeCashOpen', 'operativeCashMovements', 'operativeCashCount', 'operativeCashClose', 'operativeCashHistory', 'operativeReceivables', 'operatorCustomers',
    'pos', 'operatorCatalogProducts',
    'operativeStock', 'operativePhysicalCount', 'operativeDispatches',
    'operativeInvoices', 'operativeSales', 'operativeWarehouses', 'operativeTransfers',
    'operativeTransfersCreate', 'operativePurchaseOrders', 'operativePurchaseOrdersCreate',
    'operativeGoodsReceipts', 'operativeGoodsReceiptsCreate', 'operativeSuppliers',
    'operativePayables', 'operativeReturns', 'operativeReturnsCreate', 'operativeCreditNotes',
]);

function formatCount(value) {
    return new Intl.NumberFormat('es-NI', { maximumFractionDigits: 0 }).format(value);
}

function errorMessage(error) {
    if (!(error instanceof ApiError)) return 'No fue posible cargar tu jornada operativa.';
    if (error.status === 403) return 'Tu cuenta no está autorizada para consultar este dashboard.';
    if (error.status === 419) return 'La sesión de seguridad expiró. Intenta nuevamente.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo cargar tu jornada.';
    return error.message;
}

async function getOperativeDashboard(signal) {
    const options = { dispatchErrors: false, signal };
    try {
        return await api.get('/dashboard/operative', {}, options);
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal?.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get('/dashboard/operative', {}, options);
    }
}

function validatePayload(payload, context) {
    const data = payload?.data;
    if (!data || typeof data !== 'object' || data.branch_id !== context.branch.id) {
        throw new TypeError('El dashboard no corresponde a la sucursal autenticada.');
    }
    if (!Array.isArray(data.profiles)
        || data.profiles.length !== context.profiles.length
        || !data.profiles.every((profile) => context.profiles.includes(profile))) {
        throw new TypeError('El dashboard devolvió perfiles operativos incompatibles.');
    }
    if (!data.sections || typeof data.sections !== 'object' || Array.isArray(data.sections)) {
        throw new TypeError('El dashboard no devolvió secciones operativas válidas.');
    }

    for (const profile of context.profiles) {
        const section = data.sections[profile];
        const definition = PROFILE_META[profile];
        if (!definition || !section || typeof section !== 'object') {
            throw new TypeError(`El dashboard no devolvió la sección ${profile}.`);
        }
        definition.metrics.forEach(({ key, nullable = false }) => {
            const value = section[key];
            if (!(nullable && value === null) && (!Number.isInteger(value) || value < 0)) {
                throw new TypeError(`El campo ${profile}.${key} no es válido.`);
            }
        });
    }

    return data;
}

function collectUrls() {
    const root = document.querySelector('[data-panel-shell]');
    if (!root) return {};
    return Object.fromEntries(
        Object.entries(root.dataset)
            .filter(([key]) => key.startsWith('url') && key !== 'urlLogin')
            .map(([key, value]) => [`${key[3].toLowerCase()}${key.slice(4)}`, value]),
    );
}

function renderContext(context) {
    document.querySelector('[data-operator-name]').textContent = context.identity.name;
    document.querySelector('[data-operator-branch]').textContent = `Sucursal #${context.branch.id}`;
    document.querySelector('[data-operator-date]').textContent = new Intl.DateTimeFormat('es-NI', {
        dateStyle: 'full',
        timeZone: context.business.timezone || undefined,
    }).format(new Date());

    const profiles = document.querySelector('[data-operator-profiles]');
    profiles.replaceChildren(...context.profiles.map((profile) => {
        const item = document.createElement('li');
        item.className = 'rounded-full bg-gintly-active/60 px-3 py-1.5 text-xs font-semibold text-gintly-sidebar';
        item.textContent = PROFILE_META[profile].label;
        return item;
    }));
}

function renderStatus(data, context) {
    const grid = document.querySelector('[data-operator-status-grid]');
    grid.replaceChildren(...context.profiles.map((profile) => {
        const definition = PROFILE_META[profile];
        const values = data.sections[profile];
        const card = document.createElement('article');
        const heading = document.createElement('h3');
        const icon = document.createElement('i');
        const list = document.createElement('dl');
        card.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-5';
        heading.className = 'flex items-center gap-3 font-semibold text-gintly-text-primary';
        icon.className = `fa-solid ${definition.icon} text-gintly-brand`;
        icon.setAttribute('aria-hidden', 'true');
        heading.append(icon, definition.label);
        list.className = 'mt-4 space-y-3';
        definition.metrics.forEach((metric) => {
            const row = document.createElement('div');
            const label = document.createElement('dt');
            const value = document.createElement('dd');
            row.className = 'flex items-start justify-between gap-4 text-sm';
            label.className = 'text-gintly-text-secondary';
            value.className = 'text-end font-semibold text-gintly-text-primary';
            label.textContent = metric.label;
            value.textContent = metric.format(values[metric.key]);
            row.append(label, value);
            list.appendChild(row);
        });
        card.append(heading, list);
        return card;
    }));
}

function renderActions(context) {
    const navigation = flattenedNavigation(authorizedNavigation(context, collectUrls()));
    const unique = new Map();
    navigation.filter((entry) => ACTION_KEYS.has(entry.key)).forEach((entry) => unique.set(entry.key, entry));
    const container = document.querySelector('[data-operator-actions]');
    container.replaceChildren(...Array.from(unique.values()).map((entry) => {
        const link = document.createElement('a');
        const icon = document.createElement('i');
        const content = document.createElement('span');
        const label = document.createElement('span');
        const group = document.createElement('span');
        link.href = entry.url;
        link.className = 'flex min-h-[72px] items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-gintly-brand hover:bg-gintly-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand';
        icon.className = `fa-solid ${entry.icon} grid size-11 shrink-0 place-items-center rounded-xl bg-gintly-brand/10 text-gintly-brand`;
        icon.setAttribute('aria-hidden', 'true');
        content.className = 'min-w-0';
        label.className = 'block font-semibold text-gintly-text-primary';
        group.className = 'mt-1 block truncate text-xs text-gintly-text-secondary';
        label.textContent = entry.label;
        group.textContent = entry.group;
        content.append(label, group);
        link.append(icon, content);
        return link;
    }));
    setSectionState('operator-actions', unique.size > 0 ? 'ready' : 'empty', 'No hay acciones operativas disponibles.');
}

class OperatorDashboard {
    constructor(context) {
        this.context = context;
        this.root = document.querySelector('[data-operator-dashboard]');
        this.content = document.querySelector('[data-operator-content]');
        this.refresh = document.querySelector('[data-operator-refresh]');
        this.controller = null;
        this.pending = null;
    }

    init() {
        this.root.hidden = false;
        this.content.hidden = false;
        renderContext(this.context);
        renderActions(this.context);
        this.refresh.addEventListener('click', () => this.load());
        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-dashboard-retry="operator-status"]')) void this.load();
        });
        return this.load();
    }

    load() {
        if (this.pending) return this.pending;
        this.controller?.abort();
        this.controller = new AbortController();
        this.refresh.disabled = true;
        this.refresh.setAttribute('aria-busy', 'true');
        this.refresh.querySelector('i')?.classList.add('fa-spin');
        setSectionState('operator-status', 'loading');

        this.pending = getOperativeDashboard(this.controller.signal)
            .then((payload) => validatePayload(payload, this.context))
            .then((data) => {
                renderStatus(data, this.context);
                setSectionState('operator-status', this.context.profiles.length ? 'ready' : 'empty');
                document.querySelector('[data-operator-live]').textContent = 'Jornada operativa actualizada.';
            })
            .catch((error) => {
                if (error instanceof ApiError && error.code === 'request_aborted') return;
                setSectionState('operator-status', 'error', errorMessage(error));
            })
            .finally(() => {
                this.pending = null;
                this.refresh.disabled = false;
                this.refresh.setAttribute('aria-busy', 'false');
                this.refresh.querySelector('i')?.classList.remove('fa-spin');
            });

        return this.pending;
    }
}

export function initOperatorDashboard(context) {
    const root = document.querySelector('[data-operator-dashboard]');
    if (!root || root.dataset.initialized === 'true') return Promise.resolve();
    root.dataset.initialized = 'true';
    return new OperatorDashboard(context).init();
}
