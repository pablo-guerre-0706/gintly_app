import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { formatDateTime } from '@/dashboard/formatters';
import { compare, quantity } from '@/core/money';
import { getSessionContext } from '@/core/session-context';

const QUANTITY_PATTERN = /^\d+(?:\.\d{1,3})?$/;

async function mutate(path, data) {
    const options = { dispatchErrors: false };
    try { return await api.post(path, data, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.post(path, data, options);
    }
}

function message(error) {
    if (!(error instanceof ApiError)) return error instanceof TypeError ? error.message : 'No fue posible completar la operación.';
    if (error.status === 403) return 'La factura o el despacho no pertenecen a tu alcance operativo.';
    if ([409, 422].includes(error.status)) return error.message;
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo completar la operación.';
    return error.message;
}

class DispatchPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-dispatch-form]');
        this.delivery = null;
        this.pending = false;
    }

    async init() {
        const context = await getSessionContext();
        this.form.hidden = !context.capabilities.includes('entregas.crear');
        this.root.querySelector('[data-dispatch-load-invoice]').addEventListener('click', () => this.loadInvoice());
        this.root.querySelector('[data-dispatch-refresh]').addEventListener('click', () => this.loadList());
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        await this.loadList();
    }

    clearErrors() { this.form.querySelectorAll('[data-dispatch-error]').forEach((element) => { element.textContent = ''; }); }

    showErrors(errors) {
        let first = null;
        Object.entries(errors ?? {}).forEach(([field, values]) => {
            const key = field.split('.')[0]; const output = this.form.querySelector(`[data-dispatch-error="${key}"]`); const control = this.form.elements.namedItem(key);
            if (output) output.textContent = Array.isArray(values) ? values[0] : String(values);
            if (!first && control instanceof HTMLElement) first = control;
        }); first?.focus();
    }

    async loadList() {
        const state = this.root.querySelector('[data-dispatch-state]'); const list = this.root.querySelector('[data-dispatch-list]');
        state.hidden = false; state.textContent = 'Cargando despachos…'; list.hidden = true; this.root.setAttribute('aria-busy', 'true');
        try {
            const response = await api.get('/dispatches', { per_page: 25, sort: 'id', direction: 'desc' }, { dispatchErrors: false });
            if (!Array.isArray(response?.data)) throw new TypeError('El servidor no devolvió despachos válidos.');
            const records = response.data;
            if (!records.length) { state.textContent = 'Todavía no hay despachos registrados en tu sucursal.'; return; }
            list.replaceChildren(...records.map((record) => {
                const item = document.createElement('li'); const body = document.createElement('div'); const title = document.createElement('p'); const meta = document.createElement('p'); const status = document.createElement('span');
                item.className = 'flex items-start justify-between gap-4 p-5'; title.className = 'font-semibold'; meta.className = 'mt-1 text-sm text-gintly-text-secondary'; status.className = 'rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold';
                title.textContent = `${record.code} · Factura #${record.invoice_id}`; meta.textContent = `${record.received_by} · ${formatDateTime(record.dispatched_at)}`; status.textContent = record.status_label ?? record.status;
                body.append(title, meta); item.append(body, status); return item;
            }));
            state.hidden = true; list.hidden = false; this.root.querySelector('[data-dispatch-summary]').textContent = `${response.meta?.total ?? records.length} despachos`;
        } catch (error) { state.textContent = message(error); }
        finally { this.root.setAttribute('aria-busy', 'false'); }
    }

    async loadInvoice() {
        if (this.pending) return;
        this.clearErrors(); const id = Number(this.form.elements.invoice_id.value);
        if (!Number.isInteger(id) || id < 1) return this.showErrors({ invoice_id: ['Indica un ID de factura válido.'] });
        const button = this.root.querySelector('[data-dispatch-load-invoice]'); this.pending = true; button.disabled = true;
        try {
            const response = await api.get(`/invoices/${id}/delivery-status`, {}, { dispatchErrors: false });
            const delivery = response?.data;
            if (!delivery || !Array.isArray(delivery.lines)) throw new TypeError('La factura no devolvió un saldo de entrega válido.');
            this.delivery = delivery; this.renderLines();
        } catch (error) {
            this.delivery = null; this.root.querySelector('[data-dispatch-lines-region]').hidden = true;
            this.showErrors({ invoice_id: [message(error)] });
        } finally { this.pending = false; button.disabled = false; }
    }

    renderLines() {
        const region = this.root.querySelector('[data-dispatch-lines-region]'); const container = this.root.querySelector('[data-dispatch-lines]');
        const deliverable = this.delivery.lines.filter((line) => line.is_dispatchable === true && compare(String(line.pending_quantity), '0') > 0);
        this.root.querySelector('[data-delivery-status]').textContent = `Estado: ${this.delivery.delivery_label} · ${deliverable.length} líneas pendientes`;
        container.replaceChildren(...deliverable.map((line) => {
            const row = document.createElement('label'); const check = document.createElement('input'); const text = document.createElement('span'); const input = document.createElement('input');
            row.className = 'grid gap-3 p-4 sm:grid-cols-[auto_minmax(0,1fr)_140px] sm:items-center'; check.type = 'checkbox'; check.className = 'size-5'; check.dataset.dispatchLineCheck = String(line.sale_item_id);
            text.textContent = `${line.description ?? `Producto #${line.product_id}`} · pendiente ${line.pending_quantity}`;
            input.type = 'text'; input.inputMode = 'decimal'; input.value = String(line.pending_quantity); input.className = 'min-h-11 rounded-xl border border-slate-300 px-3 text-sm'; input.dataset.dispatchLineQuantity = String(line.sale_item_id); input.dataset.max = String(line.pending_quantity); input.disabled = true; input.setAttribute('aria-label', `Cantidad a despachar de ${line.description ?? `producto ${line.product_id}`}`);
            check.addEventListener('change', () => { input.disabled = !check.checked; this.syncSubmit(); }); input.addEventListener('input', () => this.syncSubmit());
            row.append(check, text, input); return row;
        }));
        region.hidden = false; this.syncSubmit();
    }

    syncSubmit() {
        this.root.querySelector('[data-dispatch-submit]').disabled = !this.delivery || !this.form.querySelector('[data-dispatch-line-check]:checked');
    }

    lines() {
        return Array.from(this.form.querySelectorAll('[data-dispatch-line-check]:checked')).map((check) => {
            const input = this.form.querySelector(`[data-dispatch-line-quantity="${check.dataset.dispatchLineCheck}"]`);
            const raw = input.value.trim();
            if (!QUANTITY_PATTERN.test(raw) || compare(raw, '0') <= 0 || compare(raw, input.dataset.max) > 0) throw new TypeError('Una cantidad seleccionada es inválida o supera el saldo pendiente.');
            return { sale_item_id: Number(check.dataset.dispatchLineCheck), quantity: quantity(raw) };
        });
    }

    async submit() {
        if (this.pending || !this.delivery) return;
        this.clearErrors(); let lines;
        try { lines = this.lines(); } catch (error) { this.showErrors({ lines: [error.message] }); return; }
        const receivedBy = this.form.elements.received_by.value.trim();
        if (receivedBy.length < 2) return this.showErrors({ received_by: ['Indica quién recibe la mercancía.'] });
        const button = this.root.querySelector('[data-dispatch-submit]'); this.pending = true; button.disabled = true; this.form.setAttribute('aria-busy', 'true');
        try {
            const response = await mutate('/dispatches', { invoice_id: this.delivery.invoice_id, received_by: receivedBy, notes: this.form.elements.notes.value.trim() || null, lines });
            this.root.querySelector('[data-dispatch-live]').textContent = `Despacho ${response?.data?.code ?? ''} registrado correctamente.`;
            this.form.reset(); this.delivery = null; this.root.querySelector('[data-dispatch-lines-region]').hidden = true; await this.loadList();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) this.showErrors(error.errors);
            else this.root.querySelector('[data-dispatch-live]').textContent = message(error);
        } finally { this.pending = false; button.disabled = false; this.form.setAttribute('aria-busy', 'false'); this.syncSubmit(); }
    }
}

export default function init() {
    const root = document.querySelector('[data-operative-dispatches]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new DispatchPage(root).init();
}
