import { ApiError } from '@/core/api-client';
import { compare, cost, quantity } from '@/core/money';
import { getSessionContext } from '@/core/session-context';
import { DECIMAL_3, DECIMAL_4, clearErrors, errorMessage, makeField, options, read, records, requireBodeguero, setNotice, showErrors, write } from './write-support';

class PurchaseOrderPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-operation-form]');
        this.lines = root.querySelector('[data-operation-lines]');
        this.loadController = null;
        this.submitting = false;
        this.products = [];
        this.branchId = null;
    }

    init() {
        this.root.querySelector('[data-operation-retry]').addEventListener('click', () => this.load());
        this.root.querySelector('[data-add-line]').addEventListener('click', () => this.addLine());
        this.lines.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-line]');
            if (!button || this.lines.children.length === 1) return;
            button.closest('[data-line]').remove();
            this.renumber();
        });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        return this.load();
    }

    async load() {
        this.loadController?.abort();
        const controller = new AbortController();
        this.loadController = controller;
        this.root.setAttribute('aria-busy', 'true');
        this.root.querySelector('[data-operation-loading]').hidden = false;
        this.root.querySelector('[data-operation-fatal]').hidden = true;
        this.form.hidden = true;
        try {
            const context = await getSessionContext();
            requireBodeguero(context, 'compras.crear');
            this.branchId = context.branch.id;
            this.root.querySelector('[data-operation-branch]').textContent = `Sucursal #${this.branchId}`;
            const [suppliersPayload, stockPayload] = await Promise.all([
                read('/suppliers', { status: 'aprobado', is_active: true, per_page: 100 }, controller.signal),
                read('/stock', { per_page: 100 }, controller.signal),
            ]);
            if (controller.signal.aborted) return;
            const suppliers = records(suppliersPayload).filter((item) => item.status === 'aprobado' && item.is_active === true);
            this.products = [...new Map(records(stockPayload).filter((row) => row.product?.id).map((row) => [row.product.id, row.product])).values()];
            if (!suppliers.length) throw new TypeError('No hay proveedores aprobados disponibles. Un responsable autorizado debe aprobar un proveedor antes de crear una orden.');
            if (!this.products.length) throw new TypeError('No hay productos visibles desde las existencias de tu sucursal para seleccionar en la orden.');
            options(this.form.elements.namedItem('supplier_id'), suppliers, (item) => item.name, 'Sin proveedores aprobados');
            this.lines.replaceChildren();
            this.addLine();
            this.form.hidden = false;
        } catch (error) {
            if (controller.signal.aborted) return;
            this.root.querySelector('[data-operation-fatal-message]').textContent = errorMessage(error);
            this.root.querySelector('[data-operation-fatal]').hidden = false;
        } finally {
            if (this.loadController === controller) {
                this.root.querySelector('[data-operation-loading]').hidden = true;
                this.root.setAttribute('aria-busy', 'false');
            }
        }
    }

    addLine() {
        const index = this.lines.children.length;
        const row = document.createElement('div');
        const remove = document.createElement('button');
        row.dataset.line = '';
        row.className = 'grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_8rem_9rem_auto] sm:items-start';
        row.append(
            makeField({ name: `items.${index}.product_id`, label: `Producto ${index + 1}`, options: this.products.map((product) => ({ id: product.id, label: `${product.sku} · ${product.name}` })) }),
            makeField({ name: `items.${index}.ordered_quantity`, label: 'Cantidad' }),
            makeField({ name: `items.${index}.agreed_unit_cost`, label: 'Costo unitario' }),
        );
        remove.type = 'button';
        remove.dataset.removeLine = '';
        remove.className = 'min-h-11 rounded-xl border border-slate-300 px-3 text-sm font-semibold';
        remove.textContent = 'Quitar';
        row.append(remove);
        this.lines.append(row);
    }

    renumber() {
        this.lines.querySelectorAll('[data-line]').forEach((row, index) => {
            row.querySelector('label').firstChild.textContent = `Producto ${index + 1}`;
            row.querySelectorAll('[name]').forEach((control) => { control.name = control.name.replace(/items\.\d+\./, `items.${index}.`); });
            row.querySelectorAll('[data-field-error]').forEach((node) => { node.dataset.fieldError = node.dataset.fieldError.replace(/items\.\d+\./, `items.${index}.`); });
        });
    }

    payload() {
        const errors = {};
        const supplier = Number(this.form.elements.namedItem('supplier_id').value);
        const orderedAt = this.form.elements.namedItem('ordered_at').value;
        if (!supplier) errors.supplier_id = ['Selecciona un proveedor aprobado.'];
        if (!/^\d{4}-\d{2}-\d{2}$/.test(orderedAt)) errors.ordered_at = ['Selecciona una fecha válida.'];
        const seen = new Set();
        const items = [...this.lines.querySelectorAll('[data-line]')].map((row, index) => {
            const id = Number(row.querySelector('select').value);
            const [quantityInput, costInput] = row.querySelectorAll('input');
            const qty = quantityInput.value.trim();
            const unitCost = costInput.value.trim();
            if (!id || seen.has(id)) errors[`items.${index}.product_id`] = ['Selecciona un producto sin repetirlo.'];
            seen.add(id);
            if (!DECIMAL_3.test(qty) || compare(qty, '0') <= 0) errors[`items.${index}.ordered_quantity`] = ['Indica una cantidad positiva con máximo tres decimales.'];
            if (!DECIMAL_4.test(unitCost)) errors[`items.${index}.agreed_unit_cost`] = ['Indica un costo no negativo con máximo cuatro decimales.'];
            return {
                product_id: id,
                ordered_quantity: DECIMAL_3.test(qty) ? quantity(qty) : qty,
                agreed_unit_cost: DECIMAL_4.test(unitCost) ? cost(unitCost) : unitCost,
            };
        });
        if (Object.keys(errors).length) { showErrors(this.form, errors); return null; }
        return { supplier_id: supplier, branch_id: this.branchId, ordered_at: orderedAt, notes: this.form.elements.namedItem('notes').value.trim() || null, items };
    }

    async submit() {
        if (this.submitting) return;
        clearErrors(this.form);
        const payload = this.payload();
        if (!payload) return;
        this.submitting = true;
        const button = this.root.querySelector('[data-operation-submit]');
        button.disabled = true;
        this.form.setAttribute('aria-busy', 'true');
        try {
            const response = await write('/purchase-orders', payload);
            if (!response?.data?.id || !response.data.code) throw new TypeError('El servidor no confirmó la orden creada.');
            setNotice(this.root, `Orden ${response.data.code} creada como ${response.data.status_label}. La emisión corresponde a administración.`);
            this.form.reset();
            this.lines.replaceChildren();
            this.addLine();
        } catch (error) {
            setNotice(this.root, errorMessage(error), true);
            if (error instanceof ApiError && error.status === 422) showErrors(this.form, error.errors);
        } finally {
            this.submitting = false;
            button.disabled = false;
            this.form.setAttribute('aria-busy', 'false');
        }
    }
}

export default function init() {
    const root = document.querySelector('[data-purchase-order-create]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new PurchaseOrderPage(root).init();
}
