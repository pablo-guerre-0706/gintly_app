import { ApiError } from '@/core/api-client';
import { compare, quantity } from '@/core/money';
import { getSessionContext } from '@/core/session-context';
import { DECIMAL_3, clearErrors, errorMessage, makeField, read, requireBodeguero, setNotice, showErrors, write } from './write-support';

const REASONS = Object.freeze([
    { id: 'vencido', label: 'Vencido' },
    { id: 'defecto_fabrica', label: 'Defecto de fábrica' },
    { id: 'error_despacho', label: 'Error de despacho' },
    { id: 'insatisfaccion', label: 'Insatisfacción' },
    { id: 'otro', label: 'Otro' },
]);
const DESTINATIONS = Object.freeze([
    { id: 'merma', label: 'Merma (no vuelve al stock)' },
    { id: 'reingreso', label: 'Reingreso a bodega predeterminada' },
]);

class SalesReturnPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-operation-form]');
        this.invoice = this.form.elements.namedItem('invoice_id');
        this.lines = root.querySelector('[data-operation-lines]');
        this.submitButton = root.querySelector('[data-operation-submit]');
        this.invoices = new Map();
        this.eligibleLines = [];
        this.nextPage = null;
        this.selectedInvoiceId = null;
        this.invoiceController = null;
        this.lineController = null;
        this.submitting = false;
    }

    init() {
        this.root.querySelector('[data-operation-retry]').addEventListener('click', () => void this.load());
        this.root.querySelector('[data-more-invoices]').addEventListener('click', () => void this.loadInvoices(this.nextPage));
        this.root.querySelector('[data-return-items-retry]').addEventListener('click', () => void this.loadLines(Number(this.invoice.value)));
        this.root.querySelector('[data-add-line]').addEventListener('click', () => this.addLine());
        this.invoice.addEventListener('change', () => void this.loadLines(Number(this.invoice.value)));
        this.lines.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-line]');
            if (!button || this.lines.children.length < 2) return;
            button.closest('[data-line]').remove();
            this.renumber();
        });
        this.lines.addEventListener('change', (event) => {
            if (event.target.name?.endsWith('.reason_code')) {
                const row = event.target.closest('[data-line]');
                const destination = row.querySelector('[name$=".destination"]');
                if (['vencido', 'defecto_fabrica'].includes(event.target.value)) destination.value = 'merma';
            }
        });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        return this.load();
    }

    async load() {
        this.invoiceController?.abort();
        this.lineController?.abort();
        this.root.setAttribute('aria-busy', 'true');
        this.root.querySelector('[data-operation-loading]').hidden = false;
        this.root.querySelector('[data-operation-fatal]').hidden = true;
        this.form.hidden = true;
        try {
            const context = await getSessionContext();
            requireBodeguero(context, 'devoluciones.crear');
            this.root.querySelector('[data-operation-branch]').textContent = `Sucursal #${context.branch.id}`;
            this.invoices.clear();
            this.invoice.replaceChildren();
            this.lines.replaceChildren();
            this.eligibleLines = [];
            this.selectedInvoiceId = null;
            this.submitButton.disabled = true;
            await this.loadInvoices(1);
            this.form.hidden = false;
        } catch (error) {
            this.root.querySelector('[data-operation-fatal-message]').textContent = errorMessage(error);
            this.root.querySelector('[data-operation-fatal]').hidden = false;
        } finally {
            this.root.querySelector('[data-operation-loading]').hidden = true;
            this.root.setAttribute('aria-busy', 'false');
        }
    }

    async loadInvoices(page) {
        if (!page) return;
        this.invoiceController?.abort();
        const controller = new AbortController();
        this.invoiceController = controller;
        const more = this.root.querySelector('[data-more-invoices]');
        more.disabled = true;
        try {
            const payload = await read('/sales-returns/eligible-invoices', { page, per_page: 50 }, controller.signal);
            if (controller.signal.aborted) return;
            if (!Array.isArray(payload?.data) || !payload?.meta || !payload?.links) {
                throw new TypeError('El servidor no devolvió facturas elegibles paginadas.');
            }
            if (page === 1) {
                const prompt = document.createElement('option');
                prompt.value = '';
                prompt.textContent = payload.data.length ? 'Selecciona una factura' : 'No hay facturas devolvibles';
                this.invoice.replaceChildren(prompt);
            }
            payload.data.forEach((record) => {
                if (!Number.isInteger(record.invoice_id) || this.invoices.has(record.invoice_id)) return;
                this.invoices.set(record.invoice_id, record);
                const option = document.createElement('option');
                option.value = String(record.invoice_id);
                option.textContent = `${record.folio} · ${record.customer_name || 'Cliente sin nombre'}`;
                this.invoice.append(option);
            });
            this.invoice.disabled = this.invoices.size === 0;
            this.nextPage = payload.links.next ? page + 1 : null;
            more.hidden = this.nextPage === null;
        } catch (error) {
            if (controller.signal.aborted) return;
            if (page === 1) throw error;
            setNotice(this.root, errorMessage(error), true);
        } finally {
            if (this.invoiceController === controller) more.disabled = false;
        }
    }

    async loadLines(invoiceId) {
        this.lineController?.abort();
        if (!this.invoices.has(invoiceId)) {
            this.selectedInvoiceId = null;
            this.eligibleLines = [];
            this.lines.replaceChildren();
            this.root.querySelector('[data-return-lines-fieldset]').hidden = true;
            this.submitButton.disabled = true;
            return;
        }
        const controller = new AbortController();
        this.lineController = controller;
        this.root.querySelector('[data-return-items-loading]').hidden = false;
        this.root.querySelector('[data-return-items-error]').hidden = true;
        this.submitButton.disabled = true;
        try {
            const payload = await read(`/sales-returns/eligible-invoices/${invoiceId}/items`, {}, controller.signal);
            if (controller.signal.aborted || Number(this.invoice.value) !== invoiceId) return;
            if (!Array.isArray(payload?.data)) throw new TypeError('El servidor no devolvió líneas elegibles válidas.');
            const items = payload.data;
            if (items.some((item) => !Number.isInteger(item.sale_item_id) || !DECIMAL_3.test(item.returnable_quantity))) {
                throw new TypeError('El servidor devolvió cantidades devolvibles incompatibles.');
            }
            this.selectedInvoiceId = invoiceId;
            this.eligibleLines = items;
            this.lines.replaceChildren();
            if (items.length) this.addLine();
            this.root.querySelector('[data-return-lines-fieldset]').hidden = items.length === 0;
            this.submitButton.disabled = items.length === 0;
            if (!items.length) setNotice(this.root, 'Esta factura ya no contiene productos devolvibles. Selecciona otra.', true);
        } catch (error) {
            if (controller.signal.aborted) return;
            this.root.querySelector('[data-return-items-error-message]').textContent = errorMessage(error);
            this.root.querySelector('[data-return-items-error]').hidden = false;
            this.submitButton.disabled = true;
        } finally {
            if (this.lineController === controller) this.root.querySelector('[data-return-items-loading]').hidden = true;
        }
    }

    addLine() {
        if (!this.eligibleLines.length) return;
        const index = this.lines.children.length;
        const row = document.createElement('div');
        const remove = document.createElement('button');
        row.dataset.line = '';
        row.className = 'grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-2';
        row.append(
            makeField({ name: `lines.${index}.sale_item_id`, label: `Producto ${index + 1}`, options: this.eligibleLines.map((item) => ({ id: item.sale_item_id, label: `${item.product_name} · máximo ${item.returnable_quantity}` })) }),
            makeField({ name: `lines.${index}.quantity`, label: 'Cantidad (máximo tres decimales)' }),
            makeField({ name: `lines.${index}.reason_code`, label: 'Motivo', options: REASONS }),
            makeField({ name: `lines.${index}.destination`, label: 'Destino', options: DESTINATIONS }),
        );
        remove.type = 'button';
        remove.dataset.removeLine = '';
        remove.className = 'min-h-11 justify-self-start rounded-xl border border-slate-300 px-3 text-sm font-semibold';
        remove.textContent = 'Quitar línea';
        row.append(remove);
        this.lines.append(row);
    }

    renumber() {
        this.lines.querySelectorAll('[data-line]').forEach((row, index) => {
            row.querySelector('label').firstChild.textContent = `Producto ${index + 1}`;
            row.querySelectorAll('[name]').forEach((control) => { control.name = control.name.replace(/lines\.\d+\./, `lines.${index}.`); });
            row.querySelectorAll('[data-field-error]').forEach((output) => { output.dataset.fieldError = output.dataset.fieldError.replace(/lines\.\d+\./, `lines.${index}.`); });
        });
    }

    payload() {
        const errors = {};
        const invoiceId = Number(this.invoice.value);
        if (!this.invoices.has(invoiceId) || this.selectedInvoiceId !== invoiceId) errors.invoice_id = ['Selecciona una factura elegible y espera a que carguen sus líneas.'];
        const seen = new Set();
        const lines = [...this.lines.querySelectorAll('[data-line]')].map((row, index) => {
            const itemId = Number(row.querySelector('[name$=".sale_item_id"]').value);
            const amount = row.querySelector('[name$=".quantity"]').value.trim();
            const reason = row.querySelector('[name$=".reason_code"]').value;
            const destination = row.querySelector('[name$=".destination"]').value;
            const eligible = this.eligibleLines.find((item) => item.sale_item_id === itemId);
            if (!eligible || seen.has(itemId)) errors[`lines.${index}.sale_item_id`] = ['Selecciona un producto devolvible sin repetirlo.'];
            seen.add(itemId);
            if (!DECIMAL_3.test(amount) || compare(amount, '0') <= 0) errors[`lines.${index}.quantity`] = ['Indica una cantidad positiva con máximo tres decimales.'];
            else if (eligible && compare(amount, eligible.returnable_quantity) > 0) errors[`lines.${index}.quantity`] = ['La cantidad supera lo devolvible de esta línea.'];
            if (!REASONS.some((item) => item.id === reason)) errors[`lines.${index}.reason_code`] = ['Selecciona un motivo permitido.'];
            if (!DESTINATIONS.some((item) => item.id === destination) || (destination === 'reingreso' && ['vencido', 'defecto_fabrica'].includes(reason))) {
                errors[`lines.${index}.destination`] = ['Este motivo solo permite merma.'];
            }
            return { sale_item_id: itemId, quantity: DECIMAL_3.test(amount) ? quantity(amount) : amount, reason_code: reason, destination };
        });
        if (!lines.length) errors.lines = ['Agrega al menos una línea devolvible.'];
        if (Object.keys(errors).length) { showErrors(this.form, errors); return null; }
        return { invoice_id: invoiceId, notes: this.form.elements.namedItem('notes').value.trim() || null, lines };
    }

    async submit() {
        if (this.submitting || this.submitButton.disabled) return;
        clearErrors(this.form);
        const payload = this.payload();
        if (!payload) return;
        this.submitting = true;
        this.submitButton.disabled = true;
        this.form.setAttribute('aria-busy', 'true');
        try {
            const result = await write('/sales-returns', payload);
            if (!Number.isInteger(result?.data?.id) || typeof result.data.code !== 'string') throw new TypeError('El servidor no confirmó la devolución; verifica su estado antes de intentar otra.');
            setNotice(this.root, `Devolución ${result.data.code} registrada. Estado: ${result.data.status_label}.`);
            this.invoice.value = '';
            this.selectedInvoiceId = null;
            this.eligibleLines = [];
            this.lines.replaceChildren();
            this.root.querySelector('[data-return-lines-fieldset]').hidden = true;
        } catch (error) {
            setNotice(this.root, errorMessage(error), true);
            if (error instanceof ApiError && error.status === 422) showErrors(this.form, error.errors);
        } finally {
            this.submitting = false;
            this.submitButton.disabled = this.selectedInvoiceId === null;
            this.form.setAttribute('aria-busy', 'false');
        }
    }
}

export default function init() {
    const root = document.querySelector('[data-sales-return-create]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new SalesReturnPage(root).init();
}
