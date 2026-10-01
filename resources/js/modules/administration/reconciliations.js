import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { invalidateActiveAnomalies } from '@/data/anomalies';
import { formatDateTime } from '@/dashboard/formatters';
import { clearFieldErrors, mutate, responseMessage, setButtonBusy, showFieldErrors } from '@/modules/organization/shared';
import { notify } from '@/core/notifications';

async function get(path, query = {}, signal = null) {
    const options = { dispatchErrors: false, signal };
    try { return await api.get(path, query, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal?.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false }); return api.get(path, query, options);
    }
}

function validatePage(payload, resource) {
    if (!Array.isArray(payload?.data) || !Number.isInteger(payload?.meta?.current_page) || !Number.isInteger(payload?.meta?.last_page)) throw new Error(`${resource} no devolvió una colección paginada válida.`);
    return payload;
}

class AdminReconciliations {
    constructor(root, context) {
        this.root = root; this.context = context; this.form = root.querySelector('[data-reconciliation-create]'); this.filters = root.querySelector('[data-reconciliation-filters]'); this.page = 1; this.meta = null; this.request = null; this.controller = null;
    }

    init() {
        this.form.hidden = !this.context.capabilities.includes('conciliacion.ejecutar');
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.create(); });
        this.filters.addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; void this.load(); });
        this.root.querySelector('[data-reconciliation-retry]').addEventListener('click', () => this.load());
        this.root.querySelector('[data-reconciliation-previous]').addEventListener('click', () => this.changePage(-1)); this.root.querySelector('[data-reconciliation-next]').addEventListener('click', () => this.changePage(1));
        window.addEventListener('pagehide', () => this.controller?.abort(), { once: true });
        void Promise.allSettled([this.loadBranches(), this.load()]);
    }

    async loadBranches() {
        if (this.form.hidden) return;
        const select = this.root.querySelector('[data-reconciliation-branches]'); let page = 1; let last = 1;
        do {
            const payload = validatePage(await get('/branches', { page, per_page: 50 }), 'BranchResource');
            payload.data.forEach((branch) => { if (!Number.isInteger(branch.id) || typeof branch.name !== 'string') throw new Error('BranchResource contiene campos inválidos.'); const option = document.createElement('option'); option.value = String(branch.id); option.textContent = branch.name; select.appendChild(option); });
            last = payload.meta.last_page; page += 1;
        } while (page <= last);
    }

    changePage(delta) { const next = this.page + delta; if (this.request || next < 1 || next > Number(this.meta?.last_page ?? 1)) return; this.page = next; void this.load(); }

    load() {
        if (this.request) return this.request;
        const status = new FormData(this.filters).get('status'); const query = { page: this.page, per_page: 15, sort: 'started_at', direction: 'desc' }; if (status) query.status = status;
        this.controller = new AbortController(); this.setState('loading'); this.setBusy(true);
        this.request = get('/reconciliation-runs', query, this.controller.signal).then((payload) => validatePage(payload, 'ReconciliationRunResource')).then((payload) => { this.meta = payload.meta; this.renderRows(payload.data); this.setState(payload.data.length ? 'ready' : 'empty'); }).catch((error) => { if (!(error instanceof ApiError && error.code === 'request_aborted')) this.setState('error', responseMessage(error, error?.message || 'No fue posible cargar las conciliaciones.')); }).finally(() => { this.request = null; this.controller = null; this.setBusy(false); });
        return this.request;
    }

    renderRows(records) {
        const body = this.root.querySelector('[data-reconciliation-body]'); body.replaceChildren();
        records.forEach((record) => {
            if (!Number.isInteger(record.id) || !Number.isInteger(record.anomalies_found) || typeof record.scope_label !== 'string' || typeof record.status_label !== 'string') throw new Error('ReconciliationRunResource contiene campos inválidos.');
            const row = document.createElement('tr'); const values = [record.scope_label, record.run_type === 'manual' ? 'Manual' : record.run_type, record.status_label, record.branch_id === null ? 'Todo el negocio' : `Sucursal #${record.branch_id}`, String(record.anomalies_found), formatDateTime(record.started_at, this.context.business.timezone)];
            values.forEach((value) => { const cell = document.createElement('td'); cell.className = 'px-5 py-4 text-gintly-text-secondary'; cell.textContent = value; row.appendChild(cell); }); body.appendChild(row);
        });
        this.root.querySelector('[data-reconciliation-page]').textContent = `Página ${this.meta.current_page} de ${this.meta.last_page}`; this.root.querySelector('[data-reconciliation-previous]').disabled = this.meta.current_page <= 1; this.root.querySelector('[data-reconciliation-next]').disabled = this.meta.current_page >= this.meta.last_page;
    }

    async create() {
        if (this.form.dataset.submitting === 'true') return;
        const data = new FormData(this.form); const errorBox = this.root.querySelector('[data-reconciliation-create-error]'); clearFieldErrors(this.form); errorBox.hidden = true;
        if (data.get('confirmation') !== 'on') { errorBox.textContent = 'Debes confirmar expresamente la ejecución de la conciliación.'; errorBox.hidden = false; return; }
        const button = this.root.querySelector('[data-reconciliation-create-submit]'); this.form.dataset.submitting = 'true'; setButtonBusy(button, true, 'Ejecutando…');
        try {
            const branch = data.get('branch_id'); const payload = await mutate('post', '/reconciliation-runs', { scope: data.get('scope'), branch_id: branch ? Number(branch) : null });
            if (!payload?.data || !Number.isInteger(payload.data.id)) throw new Error('El Backend no confirmó la corrida creada.');
            invalidateActiveAnomalies(); document.dispatchEvent(new CustomEvent('gintly:anomalies-invalidated')); notify({ type: 'success', message: `Conciliación #${payload.data.id} completada con ${payload.data.anomalies_found} anomalías detectadas.` });
            this.form.reset(); await this.load();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) showFieldErrors(this.form, error.errors);
            errorBox.textContent = responseMessage(error, 'No fue posible ejecutar la conciliación.'); errorBox.hidden = false;
        } finally { delete this.form.dataset.submitting; setButtonBusy(button, false); }
    }

    setBusy(busy) { this.root.setAttribute('aria-busy', String(busy)); this.root.querySelector('[data-reconciliation-filter-submit]').disabled = busy; }
    setState(state, message = '') { this.root.querySelector('[data-reconciliation-loading]').hidden = state !== 'loading'; this.root.querySelector('[data-reconciliation-error]').hidden = state !== 'error'; this.root.querySelector('[data-reconciliation-empty]').hidden = state !== 'empty'; this.root.querySelector('[data-reconciliation-content]').hidden = state !== 'ready'; if (state === 'error') this.root.querySelector('[data-reconciliation-error-message]').textContent = message; }
}

export default async function init() {
    const root = document.querySelector('[data-admin-reconciliations]'); if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; const context = await getSessionContext();
    if (context.role !== 'ROL-02' || !context.capabilities.includes('conciliacion.ver')) throw new Error('La vista requiere conciliación ROL-02.');
    new AdminReconciliations(root, context).init();
}
