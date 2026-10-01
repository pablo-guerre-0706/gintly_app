import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { getActiveAnomalies } from '@/data/anomalies';
import { ageLabel, formatDateTime } from '@/dashboard/formatters';
import { setSectionState } from '@/dashboard/section-state';

const SUMMARY_FIELDS = Object.freeze([
    ['anomalias_activas', 'Anomalías activas', 'Excepciones aún no cerradas', 'fa-triangle-exclamation', 'bg-red-100 text-red-700'],
    ['recepciones_en_discrepancia', 'Recepciones con discrepancia', 'Recepciones que no conciliaron', 'fa-boxes-packing', 'bg-amber-100 text-amber-800'],
    ['cuentas_por_pagar_congeladas', 'Cuentas por pagar congeladas', 'Obligaciones bloqueadas para revisión', 'fa-snowflake', 'bg-sky-100 text-sky-800'],
    ['cuentas_por_cobrar_vencidas', 'Cuentas por cobrar vencidas', 'Cartera fuera de plazo', 'fa-calendar-xmark', 'bg-orange-100 text-orange-800'],
    ['sesiones_caja_abiertas', 'Sesiones de caja abiertas', 'Sesiones que continúan operativas', 'fa-cash-register', 'bg-violet-100 text-violet-700'],
    ['ventas_abiertas', 'Ventas abiertas', 'Ventas aún no confirmadas', 'fa-receipt', 'bg-emerald-100 text-emerald-700'],
]);

async function getWithCsrfRecovery(path, query = {}, signal = null) {
    const options = { dispatchErrors: false, signal };
    try { return await api.get(path, query, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal?.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get(path, query, options);
    }
}

function errorMessage(error) {
    if (!(error instanceof ApiError)) return 'No fue posible cargar esta sección.';
    if (error.status === 403) return 'Tu cuenta no tiene acceso a esta información.';
    if (error.status === 419) return 'La sesión de seguridad expiró. Intenta nuevamente.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo cargar esta información.';
    return error.message || 'No fue posible cargar esta sección.';
}

function validateCollection(payload, resource) {
    if (!Array.isArray(payload?.data)) throw new Error(`${resource} devolvió una colección inválida.`);
    return payload.data;
}

function renderSummary(payload) {
    const data = payload?.data;
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('El contrato de /dashboard/admin es inválido.');
    const grid = document.querySelector('[data-admin-summary-grid]');
    grid.replaceChildren();
    SUMMARY_FIELDS.forEach(([field, label, description, iconName, theme]) => {
        const value = data[field];
        if (!Number.isInteger(value) || value < 0) throw new Error(`/dashboard/admin no entregó ${field} como entero no negativo.`);
        const card = document.createElement('article');
        const iconBox = document.createElement('span');
        const icon = document.createElement('i');
        const body = document.createElement('div');
        const title = document.createElement('h3');
        const count = document.createElement('p');
        const help = document.createElement('p');
        card.className = 'flex min-h-[150px] gap-4 rounded-2xl border border-gintly-border bg-white p-5';
        iconBox.className = `grid size-12 shrink-0 place-items-center rounded-xl ${theme}`;
        icon.className = `fa-solid ${iconName}`; icon.setAttribute('aria-hidden', 'true');
        title.className = 'text-sm font-semibold text-gintly-text-primary'; title.textContent = label;
        count.className = 'mt-2 text-3xl font-bold text-gintly-text-primary'; count.textContent = String(value);
        help.className = 'mt-1 text-xs leading-5 text-gintly-text-secondary'; help.textContent = description;
        iconBox.appendChild(icon); body.append(title, count, help); card.append(iconBox, body); grid.appendChild(card);
    });
    return true;
}

function renderAnomalies(result) {
    const list = document.querySelector('[data-admin-anomaly-list]'); list.replaceChildren();
    result.records.forEach((record) => {
        const row = document.createElement('li'); const top = document.createElement('div');
        const title = document.createElement('h3'); const severity = document.createElement('span'); const meta = document.createElement('p');
        row.className = 'rounded-2xl border border-slate-200 p-4'; top.className = 'flex flex-wrap items-start justify-between gap-2';
        title.className = 'font-semibold text-gintly-text-primary'; title.textContent = record.rule?.name ?? record.rule?.code_label ?? `Anomalía #${record.id}`;
        severity.className = `rounded-full px-2.5 py-1 text-[11px] font-semibold ${{ critica: 'bg-red-100 text-red-700', advertencia: 'bg-amber-100 text-amber-800', informativa: 'bg-sky-100 text-sky-800' }[record.severity] ?? 'bg-slate-100 text-slate-700'}`;
        severity.textContent = record.severity_label ?? record.severity;
        meta.className = 'mt-2 text-sm text-gintly-text-secondary';
        meta.textContent = [record.status_label ?? record.status, record.difference !== null && record.difference !== undefined ? `Diferencia: ${record.difference}` : null, ageLabel(record.detected_at)].filter(Boolean).join(' · ');
        top.append(title, severity); row.append(top, meta); list.appendChild(row);
    });
    return result.records.length > 0;
}

function renderCash(payload, timeZone) {
    const sessions = validateCollection(payload, 'CashSessionResource');
    const list = document.querySelector('[data-admin-cash-list]'); list.replaceChildren();
    sessions.forEach((session) => {
        if (session.expected_amount !== null || session.difference !== null) throw new Error('El Backend expuso importes de arqueo en una sesión abierta.');
        const row = document.createElement('li'); const heading = document.createElement('div');
        const name = document.createElement('h3'); const status = document.createElement('span'); const meta = document.createElement('p');
        row.className = 'py-4 first:pt-0 last:pb-0'; heading.className = 'flex items-start justify-between gap-3';
        name.className = 'font-semibold text-gintly-text-primary'; name.textContent = session.cash_register?.name ?? `Caja #${session.cash_register_id}`;
        status.className = 'rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800'; status.textContent = session.status_label ?? session.status;
        meta.className = 'mt-1 text-xs text-gintly-text-secondary'; meta.textContent = [`Abierta por usuario #${session.opened_by}`, formatDateTime(session.opened_at, timeZone)].join(' · ');
        heading.append(name, status); row.append(heading, meta); list.appendChild(row);
    });
    return sessions.length > 0;
}

class AdminDashboard {
    constructor(root, context) {
        this.root = root; this.context = context; this.refresh = root.querySelector('[data-admin-dashboard-refresh]');
        this.refreshRequest = null; this.controllers = new Map();
        this.sections = Object.freeze({
            'admin-summary': { load: (signal) => getWithCsrfRecovery('/dashboard/admin', {}, signal), render: renderSummary, empty: 'No hay contadores administrativos disponibles.' },
            'admin-anomalies': { capability: 'anomalias.ver', load: (_signal, force) => getActiveAnomalies({ force }), render: renderAnomalies, empty: 'No hay anomalías activas.' },
            'admin-cash': { capability: 'caja.gestionar', load: (signal) => getWithCsrfRecovery('/cash-sessions', { status: 'abierta', per_page: 5, sort: 'opened_at', direction: 'desc' }, signal), render: (payload) => renderCash(payload, this.context.business.timezone), empty: 'No hay sesiones de caja abiertas.' },
        });
    }

    async init() {
        this.root.hidden = false; this.renderContext(); this.filterShortcuts();
        this.refresh.addEventListener('click', () => this.loadAll({ force: true }));
        this.root.addEventListener('click', (event) => { const retry = event.target.closest('[data-dashboard-retry]'); if (retry) void this.loadSection(retry.dataset.dashboardRetry, { force: true }); });
        window.addEventListener('pagehide', () => this.controllers.forEach((controller) => controller.abort()), { once: true });
        await this.loadAll();
    }

    renderContext() {
        const date = formatDateTime(new Date().toISOString(), this.context.business.timezone);
        this.root.querySelector('[data-admin-dashboard-context]').textContent = `${date} · ${this.context.business.name || 'Negocio autenticado'} · Alcance administrativo del negocio`;
    }

    filterShortcuts() {
        let visible = 0;
        this.root.querySelectorAll('[data-admin-shortcut]').forEach((link) => {
            const allowed = this.context.capabilities.includes(link.dataset.capability);
            link.hidden = !allowed; if (allowed) visible += 1;
        });
        this.root.querySelector('[data-admin-shortcuts-empty]').hidden = visible > 0;
    }

    setRefreshLoading(loading) {
        this.refresh.disabled = loading; this.refresh.setAttribute('aria-busy', String(loading));
        this.refresh.querySelector('[data-refresh-icon]').classList.toggle('fa-spin', loading);
        this.refresh.querySelector('[data-refresh-label]').textContent = loading ? 'Actualizando…' : 'Actualizar';
    }

    loadAll({ force = false } = {}) {
        if (this.refreshRequest) return this.refreshRequest;
        this.setRefreshLoading(true);
        this.refreshRequest = Promise.allSettled(Object.keys(this.sections).map((id) => this.loadSection(id, { force }))).finally(() => { this.setRefreshLoading(false); this.refreshRequest = null; });
        return this.refreshRequest;
    }

    async loadSection(id, { force = false } = {}) {
        const definition = this.sections[id]; if (!definition) return;
        if (definition.capability && !this.context.capabilities.includes(definition.capability)) { setSectionState(id, 'error', `La capacidad ${definition.capability} no está disponible para esta cuenta.`); return; }
        this.controllers.get(id)?.abort(); const controller = new AbortController(); this.controllers.set(id, controller); setSectionState(id, 'loading');
        try {
            const payload = await definition.load(controller.signal, force); if (controller.signal.aborted) return;
            setSectionState(id, definition.render(payload) ? 'ready' : 'empty', definition.empty);
        } catch (error) {
            if (!(error instanceof ApiError && error.code === 'request_aborted')) setSectionState(id, 'error', errorMessage(error));
        } finally { if (this.controllers.get(id) === controller) this.controllers.delete(id); }
    }
}

export async function initAdminDashboard(context) {
    const root = document.querySelector('[data-admin-dashboard]');
    if (!root || root.dataset.initialized === 'true') return false;
    if (context.role !== 'ROL-02') throw new Error('El dashboard administrativo requiere ROL-02.');
    root.dataset.initialized = 'true'; await new AdminDashboard(root, context).init(); return true;
}
