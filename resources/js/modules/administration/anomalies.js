import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { invalidateActiveAnomalies } from '@/data/anomalies';
import { formatDateTime } from '@/dashboard/formatters';
import { clearFieldErrors, mutate, responseMessage, setButtonBusy, showFieldErrors } from '@/modules/organization/shared';
import { notify } from '@/core/notifications';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';

const JUSTIFIABLE = new Set(['detectada', 'notificada', 'en_revision']);

async function get(path, query = {}, signal = null) {
    const options = { dispatchErrors: false, signal };
    try { return await api.get(path, query, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal?.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false }); return api.get(path, query, options);
    }
}

function validatePage(payload) {
    if (!Array.isArray(payload?.data) || !Number.isInteger(payload?.meta?.current_page) || !Number.isInteger(payload?.meta?.last_page)) throw new Error('AnomalyResource no devolvió una colección paginada válida.');
    return payload;
}

function detailRow(label, value) {
    const box = document.createElement('div'); const term = document.createElement('dt'); const detail = document.createElement('dd');
    box.className = 'rounded-xl bg-slate-50 p-4'; term.className = 'text-xs font-semibold uppercase tracking-wide text-gintly-text-secondary'; detail.className = 'mt-1 break-words text-sm font-medium text-gintly-text-primary';
    term.textContent = label; detail.textContent = value ?? '—'; box.append(term, detail); return box;
}

class AdminAnomalies {
    constructor(root, context) {
        this.root = root; this.context = context; this.form = root.querySelector('[data-anomaly-filters]'); this.dialog = root.querySelector('[data-anomaly-dialog]');
        this.justifyForm = root.querySelector('[data-anomaly-justify]'); this.page = 1; this.meta = null; this.request = null; this.controller = null; this.current = null; this.opener = null; this.scrollOwner = Symbol('anomaly-detail');
        this.resolveForm = root.querySelector('[data-anomaly-resolve]');
    }

    init() {
        this.form.addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; void this.load(); });
        this.root.querySelector('[data-anomaly-retry]').addEventListener('click', () => this.load());
        this.root.querySelector('[data-anomaly-previous]').addEventListener('click', () => this.changePage(-1));
        this.root.querySelector('[data-anomaly-next]').addEventListener('click', () => this.changePage(1));
        this.root.querySelector('[data-anomaly-body]').addEventListener('click', (event) => { const button = event.target.closest('[data-anomaly-open]'); if (button) void this.open(Number(button.dataset.anomalyOpen)); });
        this.root.querySelector('[data-anomaly-dialog-close]').addEventListener('click', () => this.dialog.close());
        this.dialog.addEventListener('click', (event) => { if (event.target === this.dialog) this.dialog.close(); });
        this.dialog.addEventListener('close', () => { unlockScroll(this.scrollOwner); this.opener?.focus(); this.opener = null; });
        this.justifyForm.addEventListener('submit', (event) => { event.preventDefault(); void this.justify(); });
        this.resolveForm.addEventListener('submit', (event) => { event.preventDefault(); void this.resolve(); });
        window.addEventListener('pagehide', () => this.controller?.abort(), { once: true });
        void this.load();
    }

    changePage(delta) {
        const next = this.page + delta; if (this.request || next < 1 || next > Number(this.meta?.last_page ?? 1)) return;
        this.page = next; void this.load();
    }

    load() {
        if (this.request) return this.request;
        const data = new FormData(this.form); const query = { page: this.page, per_page: 15, sort: 'detected_at', direction: 'desc' };
        ['status', 'severity'].forEach((name) => { const value = data.get(name); if (value) query[name] = value; });
        this.controller = new AbortController(); this.setState('loading'); this.setBusy(true);
        this.request = get('/anomalies', query, this.controller.signal).then(validatePage).then((payload) => { this.meta = payload.meta; this.renderRows(payload.data); this.setState(payload.data.length ? 'ready' : 'empty'); }).catch((error) => {
            if (!(error instanceof ApiError && error.code === 'request_aborted')) this.setState('error', responseMessage(error, error?.message || 'No fue posible cargar las anomalías.'));
        }).finally(() => { this.request = null; this.controller = null; this.setBusy(false); });
        return this.request;
    }

    renderRows(records) {
        const body = this.root.querySelector('[data-anomaly-body]'); body.replaceChildren();
        records.forEach((record) => {
            if (!record?.rule || typeof record.rule !== 'object') throw new Error('AnomalyResource no incluyó rule.');
            const row = document.createElement('tr');
            const deviation = record.source_type === 'cash_sessions' && record.difference !== null
                ? `${record.difference} NIO (umbral)` : record.difference ?? '—';
            const values = [record.rule.name ?? record.rule.code_label ?? record.rule.code, record.severity_label, record.status_label, deviation, formatDateTime(record.detected_at)];
            values.forEach((value) => { const cell = document.createElement('td'); cell.className = 'px-5 py-4 text-gintly-text-secondary'; cell.textContent = String(value ?? '—'); row.appendChild(cell); });
            const action = document.createElement('td'); action.className = 'px-5 py-4 text-end'; const button = document.createElement('button');
            button.type = 'button'; button.className = 'min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold text-gintly-brand hover:bg-gintly-control'; button.dataset.anomalyOpen = record.id; button.textContent = 'Ver detalle'; action.appendChild(button); row.appendChild(action); body.appendChild(row);
        });
        this.root.querySelector('[data-anomaly-page]').textContent = `Página ${this.meta.current_page} de ${this.meta.last_page}`;
        this.root.querySelector('[data-anomaly-previous]').disabled = this.meta.current_page <= 1; this.root.querySelector('[data-anomaly-next]').disabled = this.meta.current_page >= this.meta.last_page;
    }

    async open(id) {
        this.current = null; this.justifyForm.hidden = true; this.resolveForm.hidden = true; this.root.querySelector('[data-anomaly-detail]').replaceChildren(); this.root.querySelector('[data-anomaly-events]').replaceChildren();
        if (!this.dialog.open) this.opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        this.root.querySelector('[data-anomaly-dialog-title]').textContent = `Anomalía #${id}`;
        if (!this.dialog.open) { lockScroll(this.scrollOwner); this.dialog.showModal(); }
        try {
            const [detail, events] = await Promise.all([get(`/anomalies/${id}`), get(`/anomalies/${id}/events`)]);
            if (!detail?.data || !Array.isArray(events?.data)) throw new Error('El detalle de anomalía no coincide con los Resources esperados.');
            this.current = detail.data; this.renderDetail(detail.data, events.data);
        } catch (error) { this.root.querySelector('[data-anomaly-detail]').append(detailRow('Error', responseMessage(error, 'No fue posible cargar el detalle.'))); }
    }

    renderDetail(record, events) {
        const cashSession = record.source_type === 'cash_sessions';
        const detail = this.root.querySelector('[data-anomaly-detail]'); detail.replaceChildren(
            detailRow('Regla', record.rule?.name ?? record.rule?.code_label ?? record.rule?.code ?? '—'),
            detailRow('Estado', record.status_label ?? record.status), detailRow('Severidad', record.severity_label ?? record.severity),
            detailRow('Sucursal', record.branch_id === null ? 'Sin sucursal específica' : `Sucursal #${record.branch_id}`),
            detailRow('Origen', record.source_type ? `${record.source_type}${record.source_id ? ` #${record.source_id}` : ''}` : '—'),
            detailRow(cashSession ? 'Esperado NIO' : 'Valor esperado', record.expected_value),
            detailRow(cashSession ? 'Contado NIO' : 'Valor observado', record.actual_value),
            detailRow(cashSession ? 'Desviación para umbral (NIO)' : 'Diferencia', record.difference),
            ...(cashSession ? [detailRow('Lectura', 'La desviación para umbral puede provenir del descuadre USD convertido. Consulta la sesión de caja para ver esperado, contado y diferencia por moneda.')] : []),
            detailRow('Detectada', formatDateTime(record.detected_at)),
            detailRow('Última intervención por', record.resolved_by ? `Usuario #${record.resolved_by}` : null),
            detailRow('Fecha de intervención', record.resolved_at ? formatDateTime(record.resolved_at) : null),
        );
        const list = this.root.querySelector('[data-anomaly-events]'); list.replaceChildren();
        events.forEach((event) => { const row = document.createElement('li'); row.className = 'rounded-xl border border-slate-200 p-3 text-sm'; row.textContent = `${event.from_status ?? 'Inicio'} → ${event.to_status} · ${formatDateTime(event.changed_at)}${event.comment ? ` · ${event.comment}` : ''}`; list.appendChild(row); });
        if (!events.length) { const row = document.createElement('li'); row.className = 'text-sm text-gintly-text-secondary'; row.textContent = 'Sin eventos registrados.'; list.appendChild(row); }
        const canJustify = this.context.role === 'ROL-02' && this.context.capabilities.includes('anomalias.justificar') && JUSTIFIABLE.has(record.status);
        this.justifyForm.hidden = !canJustify; this.justifyForm.reset(); clearFieldErrors(this.justifyForm); this.root.querySelector('[data-anomaly-justify-error]').hidden = true;
        const canResolve = this.context.role === 'ROL-01' && this.context.capabilities.includes('anomalias.resolver') && record.status !== 'resuelta';
        this.resolveForm.hidden = !canResolve; this.resolveForm.reset(); clearFieldErrors(this.resolveForm); this.root.querySelector('[data-anomaly-resolve-error]').hidden = true;
    }

    async justify() {
        if (!this.current || this.justifyForm.dataset.submitting === 'true') return;
        const data = new FormData(this.justifyForm); if (data.get('confirmation') !== 'on') { this.root.querySelector('[data-anomaly-justify-error]').textContent = 'Debes confirmar la transición auditada.'; this.root.querySelector('[data-anomaly-justify-error]').hidden = false; return; }
        const button = this.root.querySelector('[data-anomaly-justify-submit]'); clearFieldErrors(this.justifyForm); this.root.querySelector('[data-anomaly-justify-error]').hidden = true; this.justifyForm.dataset.submitting = 'true'; setButtonBusy(button, true, 'Justificando…');
        try {
            const payload = await mutate('post', `/anomalies/${this.current.id}/justify`, { reason: data.get('reason')?.toString().trim() ?? '' });
            if (!payload?.data || payload.data.status !== 'justificada') throw new Error('El Backend no confirmó la anomalía justificada.');
            invalidateActiveAnomalies(); document.dispatchEvent(new CustomEvent('gintly:anomalies-invalidated'));
            notify({ type: 'success', message: 'La anomalía quedó justificada y registrada en su bitácora.' }); await this.open(this.current.id); await this.load();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) showFieldErrors(this.justifyForm, error.errors);
            const message = error instanceof ApiError && error.status === 403 && error.code === 'SELF_RESOLUTION_NOT_ALLOWED' ? 'No puedes justificar una anomalía originada por tu propia operación.' : responseMessage(error, 'No fue posible justificar la anomalía.');
            this.root.querySelector('[data-anomaly-justify-error]').textContent = message; this.root.querySelector('[data-anomaly-justify-error]').hidden = false;
        } finally { delete this.justifyForm.dataset.submitting; setButtonBusy(button, false); }
    }

    async resolve() {
        if (!this.current || this.resolveForm.dataset.submitting === 'true') return;
        const form = this.resolveForm;
        const data = new FormData(form);
        const errorBox = this.root.querySelector('[data-anomaly-resolve-error]');
        if (data.get('confirmation') !== 'on') {
            errorBox.textContent = 'Confirma la resolución auditada antes de continuar.';
            errorBox.hidden = false;
            form.elements.confirmation.focus();
            return;
        }
        const button = this.root.querySelector('[data-anomaly-resolve-submit]');
        clearFieldErrors(form); errorBox.hidden = true; form.dataset.submitting = 'true'; setButtonBusy(button, true, 'Resolviendo…');
        try {
            const payload = await mutate('post', `/anomalies/${this.current.id}/resolve`, { comment: data.get('comment')?.toString().trim() || null });
            if (!payload?.data || payload.data.status !== 'resuelta') throw new Error('El Backend no confirmó la resolución. Verifica el detalle antes de reintentar.');
            invalidateActiveAnomalies(); document.dispatchEvent(new CustomEvent('gintly:anomalies-invalidated'));
            notify({ type: 'success', message: 'La anomalía quedó resuelta sin modificar la evidencia original.' });
            await this.open(this.current.id); await this.load();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) showFieldErrors(form, error.errors);
            errorBox.textContent = responseMessage(error, 'No fue posible confirmar la resolución.'); errorBox.hidden = false;
        } finally { delete form.dataset.submitting; setButtonBusy(button, false); }
    }

    setBusy(busy) { this.root.setAttribute('aria-busy', String(busy)); this.root.querySelector('[data-anomaly-filter-submit]').disabled = busy; }
    setState(state, message = '') {
        this.root.querySelector('[data-anomaly-loading]').hidden = state !== 'loading'; this.root.querySelector('[data-anomaly-error]').hidden = state !== 'error'; this.root.querySelector('[data-anomaly-empty]').hidden = state !== 'empty'; this.root.querySelector('[data-anomaly-content]').hidden = state !== 'ready';
        if (state === 'error') this.root.querySelector('[data-anomaly-error-message]').textContent = message;
    }
}

export default async function init() {
    const root = document.querySelector('[data-admin-anomalies]'); if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; const context = await getSessionContext();
    if (!['ROL-01', 'ROL-02'].includes(context.role) || !context.capabilities.includes('anomalias.ver')) throw new Error('La vista requiere autorización para consultar anomalías.');
    new AdminAnomalies(root, context).init();
}
