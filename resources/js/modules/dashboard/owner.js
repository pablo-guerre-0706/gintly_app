import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { getActiveAnomalies } from '@/data/anomalies';
import {
    ageLabel,
    formatDate,
    formatDateTime,
    formatMoney,
    formatPercent,
    formatValue,
} from '@/dashboard/formatters';
import { renderSalesChart } from '@/dashboard/sales-chart';
import { setSectionState } from '@/dashboard/section-state';

const KPI_ORDER = Object.freeze([
    'kpi_05',
    'ticket_promedio',
    'kpi_08',
    'kpi_02',
    'kpi_03',
]);

const KPI_ICONS = Object.freeze({
    kpi_05: ['fa-chart-line', 'bg-emerald-100 text-emerald-700'],
    ticket_promedio: ['fa-receipt', 'bg-sky-100 text-sky-700'],
    kpi_08: ['fa-hand-holding-dollar', 'bg-amber-100 text-amber-800'],
    kpi_02: ['fa-boxes-stacked', 'bg-violet-100 text-violet-700'],
    kpi_03: ['fa-triangle-exclamation', 'bg-red-100 text-red-700'],
});

function responseData(payload) {
    return payload?.data;
}

async function getWithCsrfRecovery(path, query = {}) {
    const options = { dispatchErrors: false };

    try {
        return await api.get(path, query, options);
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419) throw error;

        await initializeCsrf({ dispatchErrors: false });

        return api.get(path, query, options);
    }
}

function sectionErrorMessage(error) {
    if (!(error instanceof ApiError)) return 'No fue posible cargar esta sección.';
    if (error.status === 403) return 'Tu cuenta no tiene acceso a esta información.';
    if (error.status === 419) return 'La sesión de seguridad expiró. Intenta nuevamente.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo cargar esta información.';

    return error.message || 'No fue posible cargar esta sección.';
}

function createDefinition(term, value, valueClass = '') {
    const wrapper = document.createElement('div');
    const dt = document.createElement('dt');
    const dd = document.createElement('dd');

    wrapper.className = 'rounded-xl bg-slate-50 p-4';
    dt.className = 'text-xs font-medium text-gintly-text-secondary';
    dt.textContent = term;
    dd.className = `mt-2 text-lg font-semibold text-gintly-text-primary ${valueClass}`;
    dd.textContent = value;
    wrapper.append(dt, dd);

    return wrapper;
}

function renderKpis(payload) {
    const records = responseData(payload);
    const grid = document.querySelector('[data-kpi-grid]');

    if (!Array.isArray(records)) throw new Error('Contrato de KPI inválido.');

    const byCode = new Map(records.map((record) => [record.kpi_code, record]));
    const ordered = KPI_ORDER.map((code) => byCode.get(code)).filter(Boolean);
    grid.replaceChildren();

    ordered.forEach((record) => {
        const card = document.createElement('article');
        const iconBox = document.createElement('span');
        const icon = document.createElement('i');
        const body = document.createElement('div');
        const label = document.createElement('h3');
        const value = document.createElement('p');
        const period = document.createElement('p');
        const progress = document.createElement('p');
        const [iconName, iconTheme] = KPI_ICONS[record.kpi_code]
            ?? ['fa-chart-simple', 'bg-slate-100 text-slate-700'];

        card.className = 'flex min-h-[168px] gap-4 rounded-2xl border border-gintly-border bg-white p-6';
        iconBox.className = `grid size-[76px] shrink-0 place-items-center rounded-xl text-2xl ${iconTheme}`;
        icon.className = `fa-solid ${iconName}`;
        icon.setAttribute('aria-hidden', 'true');
        body.className = 'min-w-0 flex-1';
        label.className = 'text-sm font-medium text-gintly-text-secondary';
        label.textContent = record.label ?? record.kpi_code;
        value.className = 'mt-2 break-words text-2xl font-semibold text-gintly-text-primary';
        value.textContent = formatValue(record.value, record.unit);
        period.className = 'mt-2 text-xs text-gintly-text-secondary';
        period.textContent = record.period_start && record.period_end
            ? `${formatDate(record.period_start)} – ${formatDate(record.period_end)}`
            : 'Período no disponible';
        progress.className = 'mt-2 text-xs font-semibold text-gintly-brand';

        if (record.achievement_pct !== null && record.achievement_pct !== undefined) {
            progress.textContent = `Cumplimiento: ${formatPercent(record.achievement_pct)}`;
        } else if (record.target_value !== null && record.target_value !== undefined) {
            progress.textContent = `Meta: ${formatValue(record.target_value, record.unit)}`;
        } else {
            progress.textContent = record.calculated_at
                ? `Calculado ${formatDateTime(record.calculated_at)}`
                : 'Sin meta configurada';
        }

        iconBox.appendChild(icon);
        body.append(label, value, period, progress);
        card.append(iconBox, body);
        grid.appendChild(card);
    });

    return ordered.length > 0;
}

function renderSales(payload) {
    const report = responseData(payload);
    if (!report || !Array.isArray(report.series)) throw new Error('Contrato de ventas inválido.');

    document.querySelector('[data-sales-total]').textContent = formatMoney(report.totals?.total_sold ?? '0');
    document.querySelector('[data-sales-period]').textContent = report.period
        ? `${formatDate(report.period.from)} – ${formatDate(report.period.to)}`
        : 'Período no disponible';
    document.querySelector('[data-sales-summary]').textContent = [
        `Total vendido: ${formatMoney(report.totals?.total_sold ?? '0')}`,
        `Facturas: ${report.totals?.invoice_count ?? 0}`,
        `Ticket promedio: ${formatMoney(report.totals?.avg_ticket ?? '0')}`,
    ].join('. ');

    return renderSalesChart(
        document.querySelector('[data-sales-chart]'),
        report.series,
        formatMoney,
    );
}

function renderCash(payload, timeZone) {
    const sessions = responseData(payload);
    const list = document.querySelector('[data-cash-session-list]');

    if (!Array.isArray(sessions)) throw new Error('Contrato de sesiones de caja inválido.');

    list.replaceChildren();
    sessions.forEach((session) => {
        const row = document.createElement('li');
        const heading = document.createElement('div');
        const name = document.createElement('p');
        const status = document.createElement('span');
        const meta = document.createElement('p');
        const amount = document.createElement('p');

        row.className = 'py-4 first:pt-0 last:pb-0';
        heading.className = 'flex items-start justify-between gap-3';
        name.className = 'font-semibold text-gintly-text-primary';
        name.textContent = session.cash_register?.name ?? `Caja #${session.cash_register_id}`;
        status.className = 'rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800';
        status.textContent = session.status_label ?? session.status;
        meta.className = 'mt-1 text-xs text-gintly-text-secondary';
        meta.textContent = [
            session.cash_register?.branch_id ? `Sucursal #${session.cash_register.branch_id}` : null,
            formatDateTime(session.opened_at, timeZone),
        ].filter(Boolean).join(' · ');
        amount.className = 'mt-2 text-sm font-semibold text-gintly-brand';
        amount.textContent = `Fondo de apertura: ${formatMoney(session.opening_amount ?? '0')}`;

        heading.append(name, status);
        row.append(heading, meta, amount);
        list.appendChild(row);
    });

    return sessions.length > 0;
}

function renderInventory(payload) {
    const report = responseData(payload);
    if (!report?.totals) throw new Error('Contrato de inventario inválido.');

    const root = document.querySelector('[data-inventory-report]');
    const exactitude = String(report.totals.exactitud_pct ?? '0');
    const progress = Math.min(100, Math.max(0, Number(exactitude)));
    const summary = document.createElement('div');
    const labelRow = document.createElement('div');
    const label = document.createElement('span');
    const value = document.createElement('strong');
    const track = document.createElement('div');
    const fill = document.createElement('div');
    const definitions = document.createElement('dl');

    root.replaceChildren();
    labelRow.className = 'flex items-center justify-between gap-4 text-sm';
    label.textContent = 'Exactitud consolidada del negocio';
    value.textContent = formatPercent(exactitude);
    track.className = 'mt-3 h-3 overflow-hidden rounded-full bg-slate-100';
    fill.className = 'h-full rounded-full bg-gintly-brand';
    fill.style.width = `${Number.isFinite(progress) ? progress : 0}%`;
    definitions.className = 'mt-5 grid gap-3 sm:grid-cols-2';

    labelRow.append(label, value);
    track.appendChild(fill);
    summary.append(labelRow, track);
    definitions.append(
        createDefinition('Desviación absoluta consolidada', String(report.totals.abs_deviation ?? '0')),
        createDefinition('Faltante no justificado', formatMoney(report.totals.unjustified_shortage ?? '0')),
    );
    root.append(summary, definitions);

    return true;
}

function renderReceivables(payload) {
    const report = responseData(payload);
    if (!report?.totals) throw new Error('Contrato de cartera inválido.');

    const root = document.querySelector('[data-receivables-report]');
    const definitions = document.createElement('dl');
    definitions.className = 'grid gap-3 sm:grid-cols-2';
    definitions.append(
        createDefinition('Cartera emitida', formatMoney(report.totals.emitida ?? '0')),
        createDefinition('Recuperada', formatMoney(report.totals.recuperada ?? '0'), 'text-emerald-700'),
        createDefinition('Pendiente', formatMoney(report.totals.pendiente ?? '0'), 'text-amber-800'),
        createDefinition('Vencida', formatMoney(report.totals.vencida ?? '0'), 'text-red-700'),
    );
    root.replaceChildren(definitions);

    return true;
}

function severityTheme(severity) {
    return {
        critica: ['bg-red-100 text-red-700', 'fa-circle-exclamation'],
        advertencia: ['bg-amber-100 text-amber-800', 'fa-triangle-exclamation'],
        informativa: ['bg-sky-100 text-sky-800', 'fa-circle-info'],
    }[severity] ?? ['bg-slate-100 text-slate-700', 'fa-circle-info'];
}

function renderAlerts(result) {
    const summary = document.querySelector('[data-alert-summary]');
    const list = document.querySelector('[data-dashboard-alert-list]');

    summary.replaceChildren();
    [
        ['Detectadas', result.totals.detectada ?? 0],
        ['Notificadas', result.totals.notificada ?? 0],
        ['En revisión', result.totals.en_revision ?? 0],
    ].forEach(([label, total]) => {
        const badge = document.createElement('span');
        badge.className = 'rounded-full bg-slate-100 px-3 py-1.5 text-gintly-text-secondary';
        badge.textContent = `${label}: ${total}`;
        summary.appendChild(badge);
    });

    list.replaceChildren();
    result.records.forEach((record) => {
        const row = document.createElement('li');
        const iconBox = document.createElement('span');
        const icon = document.createElement('i');
        const body = document.createElement('div');
        const heading = document.createElement('div');
        const title = document.createElement('h3');
        const severity = document.createElement('span');
        const detail = document.createElement('p');
        const [theme, iconName] = severityTheme(record.severity);

        row.className = 'flex gap-4 rounded-2xl border border-slate-200 p-4';
        iconBox.className = `grid size-[52px] shrink-0 place-items-center rounded-lg text-xl ${theme}`;
        icon.className = `fa-solid ${iconName}`;
        icon.setAttribute('aria-hidden', 'true');
        body.className = 'min-w-0 flex-1';
        heading.className = 'flex flex-wrap items-start justify-between gap-2';
        title.className = 'font-semibold text-gintly-text-primary';
        title.textContent = record.rule?.name ?? record.rule?.code_label ?? `Anomalía #${record.id}`;
        severity.className = `rounded-full px-2.5 py-1 text-[11px] font-semibold ${theme}`;
        severity.textContent = record.severity_label ?? record.severity;
        detail.className = 'mt-2 text-sm text-gintly-text-secondary';
        detail.textContent = [
            record.rule?.code,
            record.status_label ?? record.status,
            record.difference !== null && record.difference !== undefined
                ? `Diferencia: ${record.difference}`
                : null,
            ageLabel(record.detected_at),
        ].filter(Boolean).join(' · ');

        iconBox.appendChild(icon);
        heading.append(title, severity);
        body.append(heading, detail);
        row.append(iconBox, body);
        list.appendChild(row);
    });

    return result.records.length > 0;
}

class OwnerDashboard {
    constructor(root) {
        this.root = root;
        this.content = root.querySelector('[data-dashboard-content]');
        this.refresh = root.querySelector('[data-dashboard-refresh]');
        this.contextLine = root.querySelector('[data-dashboard-context]');
        this.context = null;
        this.refreshRequest = null;

        this.sections = Object.freeze({
            kpis: {
                capability: 'panel.ver',
                load: () => getWithCsrfRecovery('/dashboard/kpis'),
                render: renderKpis,
                empty: 'No existen instantáneas KPI para el período vigente.',
            },
            sales: {
                capability: 'reportes.ver',
                load: () => getWithCsrfRecovery('/reports/ventas'),
                render: renderSales,
                empty: 'No existen ventas emitidas en el período consultado.',
            },
            cash: {
                capability: 'caja.gestionar',
                load: () => getWithCsrfRecovery('/cash-sessions', {
                    status: 'abierta',
                    per_page: 5,
                    sort: 'opened_at',
                    direction: 'desc',
                }),
                render: (payload) => renderCash(payload, this.context.business.timezone),
                empty: 'No hay sesiones de caja abiertas.',
            },
            inventory: {
                capability: 'reportes.ver',
                load: () => getWithCsrfRecovery('/reports/inventario'),
                render: renderInventory,
                empty: 'No hay información de inventario para el período.',
            },
            receivables: {
                capability: 'reportes.ver',
                load: () => getWithCsrfRecovery('/reports/cartera'),
                render: renderReceivables,
                empty: 'No hay cartera a crédito para el período.',
            },
            alerts: {
                capability: 'anomalias.ver',
                load: (force) => getActiveAnomalies({ force }),
                render: renderAlerts,
                empty: 'No hay anomalías activas.',
            },
        });
    }

    async init(context) {
        this.refresh.addEventListener('click', () => this.loadAll({ force: true }));
        this.root.addEventListener('click', (event) => {
            const retry = event.target.closest('[data-dashboard-retry]');
            if (retry) void this.loadSection(retry.dataset.dashboardRetry, { force: true });
        });

        this.context = context;
        const stockLink = this.root.querySelector('[data-owner-stock-link]');
        if (stockLink) stockLink.hidden = !context.capabilities.includes('inventario.ver');

        if (this.context.role !== 'ROL-01' || !this.context.capabilities.includes('panel.ver')) {
            throw new Error('El dashboard directivo requiere ROL-01 y panel.ver.');
        }

        this.renderContext();
        this.root.hidden = false;
        this.content.hidden = false;
        await this.loadAll();
    }

    renderContext() {
        const now = formatDateTime(new Date().toISOString(), this.context.business.timezone);
        const business = this.context.business.name || 'Negocio autenticado';

        this.contextLine.textContent = `${now} · ${business} · Vista consolidada`;
    }

    setRefreshLoading(loading) {
        this.refresh.disabled = loading;
        this.refresh.setAttribute('aria-busy', String(loading));
        this.refresh.querySelector('[data-refresh-icon]')?.classList.toggle('fa-spin', loading);
        this.refresh.querySelector('[data-refresh-label]').textContent = loading
            ? 'Actualizando…'
            : 'Actualizar';
    }

    loadAll({ force = false } = {}) {
        if (this.refreshRequest) return this.refreshRequest;

        this.setRefreshLoading(true);
        this.refreshRequest = Promise.allSettled(
            Object.keys(this.sections).map((id) => this.loadSection(id, { force })),
        ).finally(() => {
            this.setRefreshLoading(false);
            this.refreshRequest = null;
        });

        return this.refreshRequest;
    }

    async loadSection(id, { force = false } = {}) {
        const definition = this.sections[id];
        if (!definition) return;

        if (!this.context.capabilities.includes(definition.capability)) {
            setSectionState(id, 'error', `La capacidad ${definition.capability} no está disponible para esta cuenta.`);
            return;
        }

        setSectionState(id, 'loading');

        try {
            const result = await definition.load(force);
            const hasContent = definition.render(result);
            setSectionState(id, hasContent ? 'ready' : 'empty', definition.empty);
        } catch (error) {
            setSectionState(id, 'error', sectionErrorMessage(error));
        }
    }
}

export async function initOwnerDashboard(context) {
    const root = document.querySelector('[data-owner-dashboard]');
    if (!root || root.dataset.initialized === 'true') return false;

    root.dataset.initialized = 'true';
    await new OwnerDashboard(root).init(context);
    return true;
}
