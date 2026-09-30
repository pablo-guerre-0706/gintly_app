import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { formatDate, formatDateTime, formatMoney, formatPercent } from '@/dashboard/formatters';
import { renderSalesChart } from '@/dashboard/sales-chart';
import { fetchPaginatedCollection, responseMessage } from '@/modules/organization/shared';

const DEFINITIONS = Object.freeze({
    ventas: Object.freeze({
        supportsBranchFilter: true,
        fields: Object.freeze([
            ['Venta total', 'total_sold', 'money'],
            ['Documentos emitidos', 'invoice_count', 'integer'],
            ['Ticket promedio', 'avg_ticket', 'money'],
        ]),
        isEmpty: (totals) => Number(totals.invoice_count ?? 0) === 0,
    }),
    inventario: Object.freeze({
        supportsBranchFilter: false,
        fields: Object.freeze([
            ['Exactitud', 'exactitud_pct', 'percent'],
            ['Desviación absoluta', 'abs_deviation', 'decimal'],
            ['Faltante no justificado', 'unjustified_shortage', 'money'],
        ]),
        isEmpty: (totals) => Number(totals.abs_deviation ?? 0) === 0
            && Number(totals.unjustified_shortage ?? 0) === 0,
    }),
    caja: Object.freeze({
        supportsBranchFilter: true,
        fields: Object.freeze([
            ['Sesiones cerradas', 'sessions', 'integer'],
            ['Monto contado', 'counted_amount', 'money'],
            ['Diferencia absoluta', 'total_variance', 'money'],
        ]),
        isEmpty: (totals) => Number(totals.sessions ?? 0) === 0,
    }),
    cartera: Object.freeze({
        supportsBranchFilter: true,
        fields: Object.freeze([
            ['Cartera emitida', 'emitida', 'money'],
            ['Recuperada', 'recuperada', 'money'],
            ['Pendiente', 'pendiente', 'money'],
            ['Vencida', 'vencida', 'money'],
        ]),
        isEmpty: (totals) => Object.values(totals).every((value) => Number(value ?? 0) === 0),
    }),
});

function localDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function periodRange(period) {
    const end = new Date();
    const start = new Date(end);

    if (period === 'week') {
        const day = (end.getDay() + 6) % 7;
        start.setDate(end.getDate() - day);
    } else if (period === 'month') {
        start.setDate(1);
    }

    return { from: localDate(start), to: localDate(end) };
}

function formatMetric(value, kind) {
    if (kind === 'money') return formatMoney(value);
    if (kind === 'percent') return formatPercent(value);
    if (kind === 'integer') return new Intl.NumberFormat('es-NI', { maximumFractionDigits: 0 }).format(Number(value ?? 0));

    return new Intl.NumberFormat('es-NI', { maximumFractionDigits: 3 }).format(Number(value ?? 0));
}

async function getWithCsrfRecovery(path, query = {}) {
    try {
        return await api.get(path, query, { dispatchErrors: false });
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get(path, query, { dispatchErrors: false });
    }
}

class ReportSummary {
    constructor(root) {
        this.root = root;
        this.type = root.dataset.reportType;
        this.definition = DEFINITIONS[this.type];
        this.form = root.querySelector('[data-report-filters]');
        this.submit = root.querySelector('[data-report-submit]');
        this.request = null;
    }

    async init() {
        if (!this.definition) {
            this.setState('error', 'El tipo de reporte no está soportado por esta vista.');
            return;
        }

        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            void this.load();
        });
        this.root.addEventListener('click', (event) => {
            const period = event.target.closest('[data-report-period]');
            if (period) {
                this.setPeriod(period.dataset.reportPeriod);
                void this.load();
            }
            if (event.target.closest('[data-report-retry]')) void this.load();
        });

        this.setPeriod('month');
        const tasks = [this.load()];
        if (this.definition.supportsBranchFilter) tasks.push(this.loadBranches());
        await Promise.allSettled(tasks);
    }

    setPeriod(period) {
        const range = periodRange(period);
        this.form.elements.from.value = range.from;
        this.form.elements.to.value = range.to;
    }

    async loadBranches() {
        const select = this.root.querySelector('[data-branch-filter]');
        if (!select) return;

        try {
            const branches = await fetchPaginatedCollection('/branches', { per_page: 100 }, { dispatchErrors: false });
            branches.filter((branch) => branch.is_active).forEach((branch) => {
                const option = document.createElement('option');
                option.value = String(branch.id);
                option.textContent = branch.name;
                select.appendChild(option);
            });
        } catch {
            select.disabled = true;
            select.title = 'No fue posible cargar las sucursales';
        }
    }

    load() {
        if (this.request) return this.request;

        const from = this.form.elements.from.value;
        const to = this.form.elements.to.value;
        const validation = this.root.querySelector('[data-report-filter-error]');

        if (!from || !to || from > to) {
            validation.textContent = 'Selecciona un rango de fechas válido.';
            validation.hidden = false;
            (from ? this.form.elements.to : this.form.elements.from).focus();
            return Promise.resolve();
        }

        validation.hidden = true;
        this.setBusy(true);
        this.setState('loading');
        const query = { from, to };
        if (this.definition.supportsBranchFilter) {
            query.branch_id = this.form.elements.branch_id?.value ?? '';
        }

        this.request = getWithCsrfRecovery(`/reports/${this.type}`, query).then((payload) => {
            const report = payload?.data;
            if (!report?.totals || !report?.period) throw new Error('El servidor devolvió un reporte incompleto.');
            this.render(report);
            this.setState(this.definition.isEmpty(report.totals) ? 'empty' : 'ready');
        }).catch((error) => {
            this.setState('error', responseMessage(error, 'No fue posible cargar el reporte.'));
        }).finally(() => {
            this.request = null;
            this.setBusy(false);
        });

        return this.request;
    }

    render(report) {
        const totals = this.root.querySelector('[data-report-totals]');
        totals.replaceChildren();

        this.definition.fields.forEach(([label, key, kind]) => {
            const card = document.createElement('article');
            const term = document.createElement('p');
            const value = document.createElement('p');
            card.className = 'rounded-2xl border border-gintly-border bg-white p-5 shadow-sm';
            term.className = 'text-sm text-gintly-text-secondary';
            value.className = 'mt-2 text-2xl font-semibold text-gintly-text-primary';
            term.textContent = label;
            value.textContent = formatMetric(report.totals[key], kind);
            card.append(term, value);
            totals.appendChild(card);
        });

        this.root.querySelector('[data-report-period-label]').textContent = `${formatDate(report.period.from)} – ${formatDate(report.period.to)}`;
        this.root.querySelector('[data-report-generated]').textContent = report.metadata?.generated_at
            ? `Calculado ${formatDateTime(report.metadata.generated_at, report.metadata.timezone)}`
            : '';

        const seriesSection = this.root.querySelector('[data-report-series-section]');
        const series = this.root.querySelector('[data-report-series]');
        const hasSeries = this.type === 'ventas' && renderSalesChart(series, report.series, formatMoney);
        seriesSection.hidden = !hasSeries;
    }

    setBusy(busy) {
        this.root.setAttribute('aria-busy', String(busy));
        this.submit.disabled = busy;
        this.submit.setAttribute('aria-busy', String(busy));
        this.submit.querySelector('[data-submit-label]').textContent = busy ? 'Consultando…' : 'Consultar';
    }

    setState(state, message = '') {
        this.root.querySelector('[data-report-loading]').hidden = state !== 'loading';
        this.root.querySelector('[data-report-error]').hidden = state !== 'error';
        this.root.querySelector('[data-report-empty]').hidden = state !== 'empty';
        this.root.querySelector('[data-report-content]').hidden = state !== 'ready';
        if (state === 'error') this.root.querySelector('[data-report-error-message]').textContent = message;
    }
}

export default function init() {
    const root = document.querySelector('[data-report-summary]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    void new ReportSummary(root).init();
}
