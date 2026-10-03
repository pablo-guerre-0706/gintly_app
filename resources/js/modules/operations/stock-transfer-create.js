import { ApiError } from '@/core/api-client';
import { compare, quantity } from '@/core/money';
import { getSessionContext } from '@/core/session-context';
import { DECIMAL_3, clearErrors, errorMessage, makeField, options, read, records, requireBodeguero, setNotice, showErrors, write } from './write-support';

class TransferPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-operation-form]');
        this.lines = root.querySelector('[data-operation-lines]');
        this.submitting = false;
        this.loadController = null;
        this.stock = [];
    }

    init() {
        this.root.querySelector('[data-operation-retry]').addEventListener('click', () => this.load());
        this.root.querySelector('[data-add-line]').addEventListener('click', () => this.addLine());
        this.form.elements.namedItem('from_warehouse_id').addEventListener('change', () => this.refreshProducts());
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        this.lines.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove-line]');
            if (!remove || this.lines.children.length === 1) return;
            remove.closest('[data-line]').remove();
            this.renumber();
        });
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
            requireBodeguero(context, 'inventario.traspaso');
            const [warehousePayload, stockPayload] = await Promise.all([
                read('/warehouses', { is_active: true, per_page: 100 }, controller.signal),
                read('/stock', { per_page: 100 }, controller.signal),
            ]);
            if (controller.signal.aborted) return;
            const warehouses = records(warehousePayload).filter((warehouse) => warehouse.branch_id === context.branch.id && warehouse.is_active === true);
            this.stock = records(stockPayload);
            if (warehouses.length < 2) throw new TypeError('Se necesitan dos bodegas activas de tu sucursal para iniciar un traspaso desde esta pantalla.');
            options(this.form.elements.namedItem('from_warehouse_id'), warehouses, (warehouse) => warehouse.name, 'Sin bodegas');
            options(this.form.elements.namedItem('to_warehouse_id'), warehouses, (warehouse) => warehouse.name, 'Sin bodegas');
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

    availableProducts() {
        const warehouseId = Number(this.form.elements.namedItem('from_warehouse_id').value);
        return this.stock.filter((row) => row.warehouse_id === warehouseId && row.product?.id && compare(String(row.available), '0') > 0);
    }

    refreshProducts() {
        const products = this.availableProducts();
        this.lines.querySelectorAll('[data-line]').forEach((row) => {
            const select = row.querySelector('select');
            const previous = select.value;
            options(select, products.map((item) => ({ id: item.product_id, label: `${item.product.sku} · ${item.product.name} (${quantity(String(item.available))} disponibles)` })), (item) => item.label, 'Sin existencias disponibles');
            if ([...select.options].some((option) => option.value === previous)) select.value = previous;
        });
    }

    addLine() {
        const index = this.lines.children.length;
        const row = document.createElement('div');
        const remove = document.createElement('button');
        row.dataset.line = '';
        row.className = 'grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-start';
        row.append(
            makeField({ name: `items.${index}.product_id`, label: `Producto ${index + 1}`, options: [] }),
            makeField({ name: `items.${index}.quantity`, label: 'Cantidad' }),
        );
        remove.type = 'button';
        remove.dataset.removeLine = '';
        remove.className = 'min-h-11 rounded-xl border border-slate-300 px-3 text-sm font-semibold';
        remove.textContent = 'Quitar';
        row.append(remove);
        this.lines.append(row);
        this.refreshProducts();
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
        const from = Number(this.form.elements.namedItem('from_warehouse_id').value);
        const to = Number(this.form.elements.namedItem('to_warehouse_id').value);
        if (!from) errors.from_warehouse_id = ['Selecciona la bodega origen.'];
        if (!to || from === to) errors.to_warehouse_id = ['Selecciona una bodega destino distinta.'];
        const seen = new Set();
        const items = [...this.lines.querySelectorAll('[data-line]')].map((row, index) => {
            const id = Number(row.querySelector('select').value);
            const raw = row.querySelector('input').value.trim();
            if (!id || seen.has(id)) errors[`items.${index}.product_id`] = ['Selecciona un producto sin repetirlo.'];
            seen.add(id);
            if (!DECIMAL_3.test(raw) || compare(raw, '0') <= 0) errors[`items.${index}.quantity`] = ['Indica una cantidad positiva con máximo tres decimales.'];
            const stock = this.availableProducts().find((item) => item.product_id === id);
            if (id && stock && DECIMAL_3.test(raw) && compare(raw, String(stock.available)) > 0) errors[`items.${index}.quantity`] = ['La cantidad excede la existencia disponible.'];
            return { product_id: id, quantity: DECIMAL_3.test(raw) ? quantity(raw) : raw };
        });
        if (Object.keys(errors).length) { showErrors(this.form, errors); return null; }
        return { from_warehouse_id: from, to_warehouse_id: to, notes: this.form.elements.namedItem('notes').value.trim() || null, items };
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
            const response = await write('/stock-transfers', payload);
            if (!response?.data?.id || !response.data.code) throw new TypeError('El servidor no confirmó el traspaso creado.');
            setNotice(this.root, `Traspaso ${response.data.code} creado con estado ${response.data.status_label}.`);
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
    const root = document.querySelector('[data-stock-transfer-create]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new TransferPage(root).init();
}
