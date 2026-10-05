import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { quantity } from '@/core/money';

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

function errorMessage(error) {
    if (!(error instanceof ApiError)) return error instanceof TypeError ? error.message : 'No fue posible completar la operación.';
    if (error.status === 403) return 'No tienes autorización para registrar este conteo.';
    if (error.status === 409) return error.message;
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo registrar el conteo.';
    return error.message;
}

class PhysicalCountPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-physical-count-form]');
        this.searchTimer = null;
        this.productController = null;
        this.submitting = false;
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
        this.form.elements.counted_quantity.addEventListener('blur', (event) => {
            const value = event.target.value.trim();
            if (QUANTITY_PATTERN.test(value)) event.target.value = quantity(value);
        });
        await this.load();
    }

    async load() {
        const loading = this.root.querySelector('[data-physical-count-loading]');
        const fatal = this.root.querySelector('[data-physical-count-fatal]');
        loading.hidden = false;
        fatal.hidden = true;
        this.form.hidden = true;
        this.root.setAttribute('aria-busy', 'true');
        try {
            const context = await getSessionContext();
            if (context.role !== 'ROL-03' || !context.profiles.includes('bodeguero') || !context.capabilities.includes('inventario.conteo')) {
                throw new TypeError('El contexto no autoriza el registro de conteos.');
            }
            const [warehouses, stock] = await Promise.all([
                api.get('/warehouses', { is_active: true, per_page: 100 }, { dispatchErrors: false }),
                api.get('/stock', { per_page: 100 }, { dispatchErrors: false }),
            ]);
            if (!Array.isArray(warehouses?.data) || !Array.isArray(stock?.data)) {
                throw new TypeError('El servidor no devolvió catálogos operativos válidos.');
            }
            this.renderOptions(this.root.querySelector('[name="warehouse_id"]'), warehouses.data, (item) => item.name, 'No hay bodegas disponibles');
            this.renderOptions(this.root.querySelector('[name="product_id"]'), this.stockProducts(stock.data), (item) => `${item.sku} · ${item.name}`, 'No hay productos con existencias visibles');
            this.form.hidden = false;
            loading.hidden = true;
        } catch (error) {
            loading.hidden = true;
            fatal.hidden = false;
            fatal.querySelector('[data-physical-count-fatal-message]').textContent = errorMessage(error);
        } finally {
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
            const payload = await api.get('/stock', { search: search || undefined, per_page: 100 }, { signal: controller.signal, dispatchErrors: false });
            if (controller.signal.aborted) return;
            if (!Array.isArray(payload?.data)) throw new TypeError('El servidor no devolvió existencias válidas.');
            this.renderOptions(select, this.stockProducts(payload.data), (item) => `${item.sku} · ${item.name}`, 'No hay productos coincidentes');
        } catch (error) {
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
            return option;
        }));
        select.disabled = records.length === 0;
    }

    clearErrors() {
        this.root.querySelectorAll('[data-field-error]').forEach((element) => { element.textContent = ''; });
    }

    showErrors(errors) {
        let first = null;
        Object.entries(errors ?? {}).forEach(([field, messages]) => {
            const key = field.split('.')[0];
            const output = this.root.querySelector(`[data-field-error="${key}"]`);
            const control = this.form.elements.namedItem(key);
            if (output) output.textContent = Array.isArray(messages) ? messages[0] : String(messages);
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
        if (!Number.isInteger(warehouseId) || warehouseId < 1) fieldErrors.warehouse_id = ['Selecciona una bodega.'];
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
            const result = this.root.querySelector('[data-physical-count-result]');
            result.textContent = `Conteo #${count?.id} registrado con estado ${count?.status_label ?? count?.status ?? 'registrado'}.`;
            result.hidden = false;
            result.focus();
            this.form.reset();
        } catch (error) {
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
