import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { formatDate, formatDateTime, formatMoney } from '@/dashboard/formatters';
import { responseMessage } from '@/modules/organization/shared';

function contractError(resource, field) {
    return new Error(`${resource} no entregó el campo contractual ${field}.`);
}

function assertRecord(record, resource) {
    if (!record || typeof record !== 'object' || Array.isArray(record)) {
        throw contractError(resource, 'registro');
    }
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

function requiredDecimal(record, field, resource) {
    const value = record[field];
    if ((typeof value !== 'string' && typeof value !== 'number') || !/^-?\d+(?:\.\d+)?$/.test(String(value))) {
        throw contractError(resource, field);
    }
    return String(value);
}

function nullableIdentifier(record, field, resource) {
    const value = record[field];
    if (value === null) return null;
    if (!Number.isInteger(value) || value < 1) throw contractError(resource, field);
    return value;
}

function nullableDate(record, field, resource, formatter) {
    const value = nullableString(record, field, resource);
    if (value === null) return '—';
    if (Number.isNaN(new Date(value).getTime())) throw contractError(resource, field);
    return formatter(value);
}

function supplierCells(record) {
    const resource = 'SupplierResource';
    assertRecord(record, resource);
    const email = nullableString(record, 'email', resource);
    const phone = nullableString(record, 'phone', resource);
    const contact = [email, phone].filter((value) => value?.trim()).join(' · ');

    return [
        requiredString(record, 'name', resource),
        nullableString(record, 'tax_id', resource) || '—',
        contact || '—',
        requiredString(record, 'status_label', resource),
    ];
}

function payableCells(record) {
    const resource = 'AccountPayableResource';
    assertRecord(record, resource);
    if (!record.supplier || typeof record.supplier !== 'object' || Array.isArray(record.supplier)) {
        throw contractError(resource, 'supplier');
    }

    return [
        requiredString(record.supplier, 'name', `${resource}.supplier`),
        nullableDate(record, 'due_date', resource, formatDate),
        formatMoney(requiredDecimal(record, 'balance', resource)),
        requiredString(record, 'status_label', resource),
    ];
}

function anomalyCells(record) {
    const resource = 'AnomalyResource';
    assertRecord(record, resource);
    if (!record.rule || typeof record.rule !== 'object' || Array.isArray(record.rule)) {
        throw contractError(resource, 'rule');
    }

    return [
        requiredString(record.rule, 'name', 'AnomalyRuleResource'),
        requiredString(record, 'severity_label', resource),
        requiredString(record, 'status_label', resource),
        nullableDate(record, 'detected_at', resource, formatDateTime),
    ];
}

function auditCells(record) {
    const resource = 'AuditLogResource';
    assertRecord(record, resource);
    const auditableType = requiredString(record, 'auditable_type', resource);
    const auditableId = nullableIdentifier(record, 'auditable_id', resource);
    const userId = nullableIdentifier(record, 'user_id', resource);

    return [
        requiredString(record, 'action', resource),
        auditableId === null ? auditableType : `${auditableType} #${auditableId}`,
        userId === null ? '—' : `Usuario #${userId}`,
        nullableDate(record, 'created_at', resource, formatDateTime),
    ];
}

const CONFIG = Object.freeze({
    suppliers: Object.freeze({
        resource: 'SupplierResource',
        endpoint: '/suppliers',
        search: true,
        statuses: Object.freeze([['pendiente', 'Pendiente de aprobación'], ['aprobado', 'Aprobado'], ['suspendido', 'Suspendido']]),
        columns: Object.freeze(['Proveedor', 'Identificación fiscal', 'Contacto', 'Estado']),
        fields: Object.freeze(['name', 'tax_id', 'email', 'phone', 'status_label']),
        map: supplierCells,
    }),
    payables: Object.freeze({
        resource: 'AccountPayableResource',
        endpoint: '/accounts-payable',
        statuses: Object.freeze([['pendiente', 'Pendiente'], ['congelada', 'Congelada'], ['parcial', 'Pago parcial'], ['pagada', 'Pagada']]),
        columns: Object.freeze(['Proveedor', 'Vencimiento', 'Saldo', 'Estado']),
        fields: Object.freeze(['supplier.name', 'due_date', 'balance', 'status_label']),
        map: payableCells,
    }),
    anomalies: Object.freeze({
        resource: 'AnomalyResource',
        endpoint: '/anomalies',
        statuses: Object.freeze([['detectada', 'Detectada'], ['notificada', 'Notificada'], ['en_revision', 'En revisión'], ['justificada', 'Justificada'], ['resuelta', 'Resuelta']]),
        columns: Object.freeze(['Regla', 'Severidad', 'Estado', 'Detectada']),
        fields: Object.freeze(['rule.name', 'severity_label', 'status_label', 'detected_at']),
        map: anomalyCells,
    }),
    audit: Object.freeze({
        resource: 'AuditLogResource',
        endpoint: '/audit-logs',
        columns: Object.freeze(['Acción', 'Entidad', 'Usuario', 'Fecha']),
        fields: Object.freeze(['action', 'auditable_type', 'auditable_id', 'user_id', 'created_at']),
        map: auditCells,
    }),
});

function validateCollectionEnvelope(payload) {
    if (!Array.isArray(payload?.data)
        || !payload.links || typeof payload.links !== 'object' || Array.isArray(payload.links)
        || !payload.meta || typeof payload.meta !== 'object' || Array.isArray(payload.meta)
        || !Number.isInteger(payload.meta.current_page)
        || !Number.isInteger(payload.meta.last_page)) {
        throw new Error('El servidor no devolvió una colección paginada válida.');
    }
}

function cellText(value, resource) {
    if (typeof value !== 'string' && typeof value !== 'number') {
        throw contractError(resource, 'celda');
    }
    return String(value);
}

function listErrorMessage(error) {
    if (error instanceof ApiError) return responseMessage(error, 'No fue posible cargar los registros.');
    return error instanceof Error && error.message
        ? error.message
        : 'No fue posible cargar los registros.';
}

async function getWithCsrfRecovery(path, query) {
    try {
        return await api.get(path, query, { dispatchErrors: false });
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get(path, query, { dispatchErrors: false });
    }
}

class ResourceList {
    constructor(root) {
        this.root = root;
        this.config = CONFIG[root.dataset.resourceType];
        this.form = root.querySelector('[data-resource-filters]');
        this.page = 1;
        this.meta = null;
        this.request = null;
    }

    init() {
        if (!this.config) {
            this.setState('error', 'La fuente de datos de esta vista no está configurada.');
            return;
        }

        this.configureFilters();
        this.renderHead();
        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.page = 1;
            void this.load();
        });
        this.root.querySelector('[data-page-previous]').addEventListener('click', () => this.changePage(-1));
        this.root.querySelector('[data-page-next]').addEventListener('click', () => this.changePage(1));
        this.root.querySelector('[data-resource-retry]').addEventListener('click', () => this.load());
        void this.load();
    }

    configureFilters() {
        const searchField = this.root.querySelector('[data-search-field]');
        const statusField = this.root.querySelector('[data-status-field]');
        searchField.hidden = this.config.search !== true;
        statusField.hidden = !this.config.statuses;
        this.config.statuses?.forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            this.root.querySelector('[data-status-options]').appendChild(option);
        });
    }

    renderHead() {
        const row = document.createElement('tr');
        this.config.columns.forEach((label) => {
            const cell = document.createElement('th');
            cell.scope = 'col';
            cell.className = 'px-5 py-4 font-semibold text-gintly-text-primary';
            cell.textContent = label;
            row.appendChild(cell);
        });
        this.root.querySelector('[data-resource-head]').appendChild(row);
    }

    changePage(delta) {
        const next = this.page + delta;
        if (this.request || next < 1 || next > Number(this.meta?.last_page ?? 1)) return;
        this.page = next;
        void this.load();
    }

    load() {
        if (this.request) return this.request;

        const data = new FormData(this.form);
        const query = { page: this.page, per_page: 15 };
        if (this.config.search && data.get('search')?.toString().trim()) query.search = data.get('search').toString().trim();
        if (this.config.statuses && data.get('status')) query.status = data.get('status');

        this.setBusy(true);
        this.setState('loading');
        this.request = getWithCsrfRecovery(this.config.endpoint, query)
            .then((payload) => {
                validateCollectionEnvelope(payload);
                this.meta = payload.meta;
                this.renderRows(payload.data);
                this.setState(payload.data.length > 0 ? 'ready' : 'empty');
            })
            .catch((error) => this.setState('error', listErrorMessage(error)))
            .finally(() => {
                this.request = null;
                this.setBusy(false);
            });

        return this.request;
    }

    renderRows(records) {
        const body = this.root.querySelector('[data-resource-body]');
        body.replaceChildren();
        records.forEach((record) => {
            const row = document.createElement('tr');
            const values = this.config.map(record);
            if (values.length !== this.config.columns.length) throw contractError(this.config.resource, 'columnas');
            values.forEach((value) => {
                const cell = document.createElement('td');
                cell.className = 'px-5 py-4 text-gintly-text-secondary';
                cell.textContent = cellText(value, this.config.resource);
                row.appendChild(cell);
            });
            body.appendChild(row);
        });
        this.root.querySelector('[data-page-status]').textContent = `Página ${this.meta.current_page} de ${this.meta.last_page}`;
        this.root.querySelector('[data-page-previous]').disabled = this.meta.current_page <= 1;
        this.root.querySelector('[data-page-next]').disabled = this.meta.current_page >= this.meta.last_page;
    }

    setBusy(busy) {
        this.root.setAttribute('aria-busy', String(busy));
        this.root.querySelector('[data-resource-submit]').disabled = busy;
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
    root.dataset.initialized = 'true';
    new ResourceList(root).init();
}
