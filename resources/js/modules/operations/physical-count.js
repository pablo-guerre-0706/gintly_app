import { ApiError } from '@/core/api-client';
import { quantity } from '@/core/money';
import { read, write as mutate, errorMessage } from './write-support';
import { inventoryScope } from '@/modules/inventory/stock-data';
import { allPages, countSnapshot, productUnit } from '@/modules/inventory/stock-contract';

const QUANTITY_PATTERN = /^\d+(?:\.\d{1,3})?$/;

class PhysicalCountPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-physical-count-form]');
        this.searchTimer = null;
        this.productController = null;
        this.submitting = false;
        this.scope = null;
        this.loading = false;
    }

    async init() {
        this.root.querySelector('[data-physical-count-retry]').addEventListener('click', () => this.load());
        this.root.querySelector('[data-product-search]').addEventListener('input', (event) => {
            window.clearTimeout(this.searchTimer);
            const value = event.target.value.trim();
            this.searchTimer = window.setTimeout(() => {
                if (value.length === 0 || value.length >= 2) void this.loadProducts(value);
            }, 300);
        });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        this.form.elements.warehouse_id.addEventListener('change', () => void this.loadProducts(this.root.querySelector('[data-product-search]').value.trim()));
        this.form.elements.product_id.addEventListener('change', () => this.updateUnit());
        this.form.elements.counted_quantity.addEventListener('blur', (event) => {
            const value = event.target.value.trim();
            if (QUANTITY_PATTERN.test(value)) event.target.value = quantity(value);
        });
        await this.load();
    }

    async load() {
        if (this.loading) return;
        this.loading = true;
        const loading = this.root.querySelector('[data-physical-count-loading]');
        const fatal = this.root.querySelector('[data-physical-count-fatal]');
        loading.hidden = false;
        fatal.hidden = true;
        this.form.hidden = true;
        this.root.setAttribute('aria-busy', 'true');
        try {
            this.scope = await inventoryScope();
            const context = this.scope.context;
            if (context.role !== 'ROL-03' || !context.profiles.includes('bodeguero') || !context.capabilities.includes('inventario.conteo')) {
                throw new TypeError('El contexto no autoriza el registro de conteos.');
            }
            this.renderOptions(this.root.querySelector('[name="warehouse_id"]'), this.scope.warehouses, (item) => item.name, 'No tienes bodegas activas asignadas');
            await this.loadProducts('');
            this.root.querySelector('[data-physical-count-submit]').disabled = this.scope.warehouses.length === 0;
            this.form.hidden = false;
            loading.hidden = true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) window.location.assign(document.querySelector('meta[name="login-url"]').content);
            loading.hidden = true;
            fatal.hidden = false;
            fatal.querySelector('[data-physical-count-fatal-message]').textContent = errorMessage(error);
        } finally {
            this.loading = false;
            this.root.setAttribute('aria-busy', 'false');
        }
    }

    async loadProducts(search) {
        this.productController?.abort();
        const controller = new AbortController();
        this.productController = controller;
        const select = this.root.querySelector('[name="product_id"]');
        select.disabled = true;
        try {
            const rows = this.scope.warehouses.length ? await allPages(read, '/stock', {
                search: search || undefined, warehouse_id: Number(this.form.elements.warehouse_id.value) || undefined,
            }, controller.signal) : [];
            if (controller.signal.aborted) return;
            this.renderOptions(select, this.stockProducts(rows), (item) => `${item.sku} · ${item.name} · ${productUnit(item)}`, 'No hay productos con saldo consultable');
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) window.location.assign(document.querySelector('meta[name="login-url"]').content);
            if (!controller.signal.aborted) this.renderOptions(select, [], () => '', errorMessage(error));
        } finally { if (this.productController === controller) this.productController = null; }
    }

    stockProducts(rows) {
        return [...new Map(rows.filter((row) => row.product?.id).map((row) => [row.product.id, row.product])).values()];
    }

    renderOptions(select, records, label, empty) {
        const prompt = document.createElement('option');
        prompt.value = '';
        prompt.textContent = records.length ? 'Selecciona una opción' : empty;
        select.replaceChildren(prompt, ...records.map((record) => {
            const option = document.createElement('option');
            option.value = String(record.id);
            option.textContent = label(record);
            if (select.name === 'product_id') option.dataset.unit = productUnit(record);
            return option;
        }));
        select.disabled = records.length === 0;
        if (select.name === 'product_id') this.updateUnit();
    }

    updateUnit() {
        const unit = this.form.elements.product_id.selectedOptions[0]?.dataset.unit;
        this.root.querySelector('[data-physical-count-unit]').textContent = unit
            ? `Cantidad expresada en ${unit}. Admite hasta tres decimales.`
            : 'Selecciona un producto para conocer su unidad de medida.';
    }

    clearErrors() {
        this.form.querySelectorAll('[aria-invalid="true"]').forEach((element) => element.removeAttribute('aria-invalid'));
        this.root.querySelectorAll('[data-field-error]').forEach((element) => { element.textContent = ''; });
    }

    showErrors(errors) {
        let first = null;
        Object.entries(errors ?? {}).forEach(([field, messages]) => {
            const key = field.split('.')[0];
            const output = this.root.querySelector(`[data-field-error="${key}"]`);
            const control = this.form.elements.namedItem(key);
            if (output) output.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            if (control instanceof HTMLElement) control.setAttribute('aria-invalid', 'true');
            if (!first && control instanceof HTMLElement) first = control;
        });
        first?.focus();
    }

    async submit() {
        if (this.submitting) return;
        this.clearErrors();
        const data = new FormData(this.form);
        const counted = String(data.get('counted_quantity') ?? '').trim();
        const warehouseId = Number(data.get('warehouse_id'));
        const productId = Number(data.get('product_id'));
        const fieldErrors = {};
        if (!this.scope?.warehouses.some((warehouse) => warehouse.id === warehouseId)) fieldErrors.warehouse_id = ['Selecciona una bodega activa asignada.'];
        if (!Number.isInteger(productId) || productId < 1) fieldErrors.product_id = ['Selecciona un producto.'];
        if (Object.keys(fieldErrors).length) { this.showErrors(fieldErrors); return; }
        if (!QUANTITY_PATTERN.test(counted)) {
            this.showErrors({ counted_quantity: ['Usa un número no negativo con máximo tres decimales.'] });
            return;
        }
        const payload = {
            warehouse_id: warehouseId,
            product_id: productId,
            counted_quantity: quantity(counted),
            notes: String(data.get('notes') ?? '').trim() || null,
        };
        const button = this.root.querySelector('[data-physical-count-submit]');
        this.submitting = true;
        button.disabled = true;
        this.form.setAttribute('aria-busy', 'true');
        try {
            const response = await mutate('/physical-counts', payload);
            const count = response?.data;
            if (!Number.isInteger(count?.id)) throw new TypeError('El servidor no confirmó el conteo; verifica el listado antes de reintentar.');
            const snapshot = countSnapshot(count);
            const unit = productUnit(count.product);
            const result = this.root.querySelector('[data-physical-count-result]');
            result.textContent = `Conteo #${snapshot.id} registrado · ${snapshot.label}. Unidad: ${unit}. Cantidad física: ${snapshot.counted}; sistema al contar: ${snapshot.system}; diferencia histórica: ${snapshot.difference}. El registro guardado no puede editarse por el operador.`;
            result.hidden = false;
            result.focus();
            this.form.reset();
            this.updateUnit();
            await this.loadProducts('');
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) window.location.assign(document.querySelector('meta[name="login-url"]').content);
            if (error instanceof ApiError && error.status === 422) this.showErrors(error.errors);
            else {
                const result = this.root.querySelector('[data-physical-count-result]');
                result.textContent = errorMessage(error);
                result.hidden = false;
                result.focus();
            }
        } finally {
            this.submitting = false;
            button.disabled = false;
            this.form.setAttribute('aria-busy', 'false');
        }
    }
}

export default function init() {
    const root = document.querySelector('[data-physical-count-page]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new PhysicalCountPage(root).init();
}
