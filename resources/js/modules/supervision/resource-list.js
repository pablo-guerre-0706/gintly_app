import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { formatDate, formatDateTime, formatMoney, formatPercent, formatValue } from '@/dashboard/formatters';
import { responseMessage } from '@/modules/organization/shared';

const contractError = (resource, field) => new Error(`${resource} no entregó el campo contractual ${field}.`);

function assertRecord(record, resource) {
    if (!record || typeof record !== 'object' || Array.isArray(record)) throw contractError(resource, 'registro');
}

function requiredString(record, field, resource) {
    const value = record[field];
    if (typeof value !== 'string' || value.trim() === '') throw contractError(resource, field);
    return value;
}

function nullableString(record, field, resource) {
    const value = record[field];
    if (value === null) return null;
    if (typeof value !== 'string') throw contractError(resource, field);
    return value;
}

function decimal(record, field, resource, nullable = false) {
    const value = record[field];
    if (nullable && value === null) return null;
    if ((typeof value !== 'string' && typeof value !== 'number') || !/^-?\d+(?:\.\d+)?$/.test(String(value))) throw contractError(resource, field);
    return String(value);
}

function integer(record, field, resource, nullable = false) {
    const value = record[field];
    if (nullable && value === null) return null;
    if (!Number.isInteger(value)) throw contractError(resource, field);
    return value;
}

function boolean(record, field, resource) {
    if (typeof record[field] !== 'boolean') throw contractError(resource, field);
    return record[field];
}

function nested(record, field, resource) {
    const value = record[field];
    if (!value || typeof value !== 'object' || Array.isArray(value)) throw contractError(resource, field);
    return value;
}

function nullableDate(record, field, resource, formatter = formatDateTime) {
    const value = nullableString(record, field, resource);
    if (value === null) return '—';
    if (Number.isNaN(new Date(value).getTime())) throw contractError(resource, field);
    return formatter(value);
}

function yesNo(value) { return value ? 'Sí' : 'No'; }

function supplierCells(record) {
    const resource = 'SupplierResource'; assertRecord(record, resource);
    const email = nullableString(record, 'email', resource);
    const phone = nullableString(record, 'phone', resource);
    return [requiredString(record, 'name', resource), nullableString(record, 'tax_id', resource) || '—', [email, phone].filter(Boolean).join(' · ') || '—', requiredString(record, 'status_label', resource)];
}

function payableCells(record) {
    const resource = 'AccountPayableResource'; assertRecord(record, resource);
    const supplier = nested(record, 'supplier', resource);
    return [requiredString(supplier, 'name', `${resource}.supplier`), nullableDate(record, 'due_date', resource, formatDate), formatMoney(decimal(record, 'balance', resource)), requiredString(record, 'status_label', resource)];
}

function auditCells(record) {
    const resource = 'AuditLogResource'; assertRecord(record, resource);
    const type = requiredString(record, 'auditable_type', resource);
    const auditableId = integer(record, 'auditable_id', resource, true);
    const userId = integer(record, 'user_id', resource, true);
    return [requiredString(record, 'action', resource), auditableId === null ? type : `${type} #${auditableId}`, userId === null ? '—' : `Usuario #${userId}`, nullableDate(record, 'created_at', resource)];
}

function warehouseCells(record) {
    const resource = 'WarehouseResource'; assertRecord(record, resource);
    const branch = nested(record, 'branch', resource);
    return [requiredString(record, 'name', resource), requiredString(branch, 'name', `${resource}.branch`), yesNo(boolean(record, 'is_default', resource)), boolean(record, 'is_active', resource) ? 'Activa' : 'Inactiva'];
}

function cashRegisterCells(record) {
    const resource = 'CashRegisterResource'; assertRecord(record, resource);
    const branch = nested(record, 'branch', resource);
    return [requiredString(record, 'name', resource), requiredString(branch, 'name', `${resource}.branch`), boolean(record, 'is_active', resource) ? 'Activa' : 'Inactiva', nullableDate(record, 'created_at', resource)];
}

function purchaseOrderCells(record) {
    const resource = 'PurchaseOrderResource'; assertRecord(record, resource);
    const supplier = nested(record, 'supplier', resource);
    return [requiredString(record, 'code', resource), requiredString(supplier, 'name', `${resource}.supplier`), formatMoney(decimal(record, 'expected_total', resource)), requiredString(record, 'status_label', resource), nullableDate(record, 'ordered_at', resource, formatDate)];
}

function goodsReceiptCells(record) {
    const resource = 'GoodsReceiptResource'; assertRecord(record, resource);
    const total = decimal(record, 'supplier_invoice_total', resource, true);
    return [`Recepción #${integer(record, 'id', resource)}`, `OC #${integer(record, 'purchase_order_id', resource)}`, total === null ? '—' : formatMoney(total), requiredString(record, 'match_status_label', resource), nullableDate(record, 'received_at', resource)];
}

function invoiceCells(record) {
    const resource = 'InvoiceResource'; assertRecord(record, resource);
    return [requiredString(record, 'folio', resource), formatMoney(decimal(record, 'total', resource)), requiredString(record, 'payment_type_label', resource), requiredString(record, 'status_label', resource), nullableDate(record, 'issued_at', resource)];
}

function physicalCountCells(record) {
    const resource = 'PhysicalCountResource'; assertRecord(record, resource);
    const product = nested(record, 'product', resource);
    const warehouse = nested(record, 'warehouse', resource);
    return [requiredString(product, 'name', `${resource}.product`), requiredString(warehouse, 'name', `${resource}.warehouse`), decimal(record, 'difference', resource), requiredString(record, 'status_label', resource), nullableDate(record, 'counted_at', resource)];
}

function kpiSnapshotCells(record) {
    const resource = 'KpiSnapshotResource'; assertRecord(record, resource);
    const achievement = decimal(record, 'achievement_pct', resource, true);
    return [requiredString(record, 'label', resource), formatValue(decimal(record, 'value', resource), requiredString(record, 'unit', resource)), requiredString(record, 'period_type', resource), achievement === null ? '—' : formatPercent(achievement), nullableDate(record, 'calculated_at', resource)];
}

function reportDefinitionCells(record) {
    const resource = 'ReportDefinitionResource'; assertRecord(record, resource);
    return [requiredString(record, 'name', resource), requiredString(record, 'report_type', resource), boolean(record, 'is_scheduled', resource) ? 'Programada' : 'Manual', nullableString(record, 'schedule_cron', resource) || '—', nullableDate(record, 'created_at', resource)];
}

const filter = (name, label, placeholder, options) => Object.freeze({ name, label, placeholder, options: Object.freeze(options) });

const CONFIG = Object.freeze({
    suppliers: Object.freeze({ resource: 'SupplierResource', endpoint: '/suppliers', search: true, filter: filter('status', 'Estado', 'Todos los estados', [['pendiente', 'Pendiente de aprobación'], ['aprobado', 'Aprobado'], ['suspendido', 'Suspendido']]), columns: Object.freeze(['Proveedor', 'Identificación fiscal', 'Contacto', 'Estado']), map: supplierCells }),
    payables: Object.freeze({ resource: 'AccountPayableResource', endpoint: '/accounts-payable', filter: filter('status', 'Estado', 'Todos los estados', [['pendiente', 'Pendiente'], ['congelada', 'Congelada'], ['parcial', 'Pago parcial'], ['pagada', 'Pagada']]), columns: Object.freeze(['Proveedor', 'Vencimiento', 'Saldo', 'Estado']), map: payableCells }),
    audit: Object.freeze({ resource: 'AuditLogResource', endpoint: '/audit-logs', columns: Object.freeze(['Acción', 'Entidad', 'Usuario', 'Fecha']), map: auditCells }),
    warehouses: Object.freeze({ resource: 'WarehouseResource', endpoint: '/warehouses', filter: filter('is_active', 'Estado', 'Todas', [['1', 'Activas'], ['0', 'Inactivas']]), columns: Object.freeze(['Bodega', 'Sucursal', 'Predeterminada', 'Estado']), map: warehouseCells }),
    cashRegisters: Object.freeze({ resource: 'CashRegisterResource', endpoint: '/cash-registers', filter: filter('is_active', 'Estado', 'Todas', [['1', 'Activas'], ['0', 'Inactivas']]), columns: Object.freeze(['Caja', 'Sucursal', 'Estado', 'Creada']), map: cashRegisterCells }),
    purchaseOrders: Object.freeze({ resource: 'PurchaseOrderResource', endpoint: '/purchase-orders', filter: filter('status', 'Estado', 'Todos los estados', [['borrador', 'Borrador'], ['emitida', 'Emitida'], ['parcial', 'Recibida parcialmente'], ['recibida', 'Recibida'], ['cancelada', 'Cancelada']]), columns: Object.freeze(['Orden', 'Proveedor', 'Total esperado', 'Estado', 'Fecha']), map: purchaseOrderCells }),
    goodsReceipts: Object.freeze({ resource: 'GoodsReceiptResource', endpoint: '/goods-receipts', filter: filter('match_status', 'Conciliación', 'Todos los estados', [['ok', 'Conforme'], ['discrepancia', 'Con discrepancia'], ['bloqueada', 'Bloqueada']]), columns: Object.freeze(['Recepción', 'Orden', 'Total factura', 'Conciliación', 'Recibida']), map: goodsReceiptCells }),
    invoices: Object.freeze({ resource: 'InvoiceResource', endpoint: '/invoices', filter: filter('status', 'Estado', 'Todos los estados', [['emitida', 'Emitida'], ['anulada', 'Anulada']]), columns: Object.freeze(['Folio', 'Total', 'Pago', 'Estado', 'Emitida']), map: invoiceCells }),
    physicalCounts: Object.freeze({ resource: 'PhysicalCountResource', endpoint: '/physical-counts', filter: filter('status', 'Estado', 'Todos los estados', [['abierto', 'Abierto'], ['justificado', 'Justificado'], ['ajustado', 'Ajustado']]), columns: Object.freeze(['Producto', 'Bodega', 'Diferencia', 'Estado', 'Contado']), map: physicalCountCells }),
    kpiSnapshots: Object.freeze({ resource: 'KpiSnapshotResource', endpoint: '/kpi-snapshots', filter: filter('period_type', 'Período', 'Todos los períodos', [['diario', 'Diario'], ['semanal', 'Semanal'], ['mensual', 'Mensual'], ['anual', 'Anual']]), columns: Object.freeze(['Indicador', 'Valor', 'Período', 'Cumplimiento', 'Calculado']), map: kpiSnapshotCells }),
    reportDefinitions: Object.freeze({ resource: 'ReportDefinitionResource', endpoint: '/report-definitions', filter: filter('report_type', 'Tipo', 'Todos los tipos', [['ventas', 'Ventas'], ['cartera', 'Cartera'], ['inventario', 'Inventario'], ['caja', 'Caja'], ['consolidado', 'Consolidado']]), columns: Object.freeze(['Definición', 'Tipo', 'Ejecución', 'Programación', 'Creada']), map: reportDefinitionCells }),
});

function validateEnvelope(payload) {
    if (!Array.isArray(payload?.data) || !payload.links || typeof payload.links !== 'object' || !payload.meta || typeof payload.meta !== 'object' || !Number.isInteger(payload.meta.current_page) || !Number.isInteger(payload.meta.last_page)) throw new Error('El servidor no devolvió una colección paginada válida.');
}

function listErrorMessage(error) {
    if (error instanceof ApiError) return responseMessage(error, 'No fue posible cargar los registros.');
    return error instanceof Error && error.message ? error.message : 'No fue posible cargar los registros.';
}

async function getWithCsrfRecovery(path, query, signal) {
    const options = { dispatchErrors: false, signal };
    try { return await api.get(path, query, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get(path, query, options);
    }
}

class ResourceList {
    constructor(root) {
        this.root = root; this.config = CONFIG[root.dataset.resourceType];
        this.form = root.querySelector('[data-resource-filters]'); this.page = 1;
        this.meta = null; this.request = null; this.controller = null;
    }

    init() {
        if (!this.config) { this.setState('error', 'La fuente de datos de esta vista no está configurada.'); return; }
        this.configureFilters(); this.renderHead();
        this.form.addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; void this.load(); });
        this.root.querySelector('[data-page-previous]').addEventListener('click', () => this.changePage(-1));
        this.root.querySelector('[data-page-next]').addEventListener('click', () => this.changePage(1));
        this.root.querySelector('[data-resource-retry]').addEventListener('click', () => this.load());
        window.addEventListener('pagehide', () => this.controller?.abort(), { once: true });
        void this.load();
    }

    configureFilters() {
        const searchField = this.root.querySelector('[data-search-field]');
        const filterField = this.root.querySelector('[data-filter-field]');
        const select = this.root.querySelector('[data-filter-options]');
        searchField.hidden = this.config.search !== true;
        filterField.hidden = !this.config.filter;
        if (!this.config.filter) return;
        this.root.querySelector('[data-filter-label]').textContent = this.config.filter.label;
        this.root.querySelector('[data-filter-placeholder]').textContent = this.config.filter.placeholder;
        select.name = this.config.filter.name;
        this.config.filter.options.forEach(([value, label]) => {
            const option = document.createElement('option'); option.value = value; option.textContent = label; select.appendChild(option);
        });
    }

    renderHead() {
        const row = document.createElement('tr');
        this.config.columns.forEach((label) => { const cell = document.createElement('th'); cell.scope = 'col'; cell.className = 'px-5 py-4 font-semibold text-gintly-text-primary'; cell.textContent = label; row.appendChild(cell); });
        this.root.querySelector('[data-resource-head]').appendChild(row);
    }

    changePage(delta) {
        const next = this.page + delta;
        if (this.request || next < 1 || next > Number(this.meta?.last_page ?? 1)) return;
        this.page = next; void this.load();
    }

    load() {
        if (this.request) return this.request;
        const data = new FormData(this.form); const query = { page: this.page, per_page: 15 };
        const search = data.get('search')?.toString().trim();
        if (this.config.search && search) query.search = search;
        if (this.config.filter) {
            const value = data.get(this.config.filter.name);
            if (value !== null && value !== '') query[this.config.filter.name] = value;
        }
        this.controller = new AbortController(); this.setBusy(true); this.setState('loading');
        this.request = getWithCsrfRecovery(this.config.endpoint, query, this.controller.signal)
            .then((payload) => { validateEnvelope(payload); this.meta = payload.meta; this.renderRows(payload.data); this.setState(payload.data.length ? 'ready' : 'empty'); })
            .catch((error) => { if (!(error instanceof ApiError && error.code === 'request_aborted')) this.setState('error', listErrorMessage(error)); })
            .finally(() => { this.request = null; this.controller = null; this.setBusy(false); });
        return this.request;
    }

    renderRows(records) {
        const body = this.root.querySelector('[data-resource-body]'); body.replaceChildren();
        records.forEach((record) => {
            const row = document.createElement('tr'); const values = this.config.map(record);
            if (values.length !== this.config.columns.length) throw contractError(this.config.resource, 'columnas');
            values.forEach((value) => { if (typeof value !== 'string' && typeof value !== 'number') throw contractError(this.config.resource, 'celda'); const cell = document.createElement('td'); cell.className = 'px-5 py-4 text-gintly-text-secondary'; cell.textContent = String(value); row.appendChild(cell); });
            body.appendChild(row);
        });
        this.root.querySelector('[data-page-status]').textContent = `Página ${this.meta.current_page} de ${this.meta.last_page}`;
        this.root.querySelector('[data-page-previous]').disabled = this.meta.current_page <= 1;
        this.root.querySelector('[data-page-next]').disabled = this.meta.current_page >= this.meta.last_page;
    }

    setBusy(busy) {
        this.root.setAttribute('aria-busy', String(busy)); this.root.querySelector('[data-resource-submit]').disabled = busy;
        this.root.querySelector('[data-page-previous]').disabled = busy || this.page <= 1;
        this.root.querySelector('[data-page-next]').disabled = busy || this.page >= Number(this.meta?.last_page ?? 1);
    }

    setState(state, message = '') {
        this.root.querySelector('[data-resource-loading]').hidden = state !== 'loading';
        this.root.querySelector('[data-resource-error]').hidden = state !== 'error';
        this.root.querySelector('[data-resource-empty]').hidden = state !== 'empty';
        this.root.querySelector('[data-resource-content]').hidden = state !== 'ready';
        if (state === 'error') this.root.querySelector('[data-resource-error-message]').textContent = message;
    }
}

export default function init() {
    const root = document.querySelector('[data-supervision-list]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; new ResourceList(root).init();
}
