import { ApiError } from '@/core/api-client';
import { authorizedNavigation, flattenedNavigation } from '@/shell/navigation';
import { errorMessage, read } from '@/modules/operations/write-support';
import { countSnapshot, stockRecord, productUnit, minimumAlert, validatePage } from './stock-contract';
import { inventoryPage, inventoryScope } from './stock-data';

const SOURCES = Object.freeze({ stock: '/stock', counts: '/physical-counts', alerts: '/stock/alerts' });
const node = (tag, text, className = '') => {
    const element = document.createElement(tag);
    element.textContent = text;
    element.className = className;
    return element;
};

function term(label, value) {
    const wrapper = node('div', '', 'min-w-0');
    wrapper.append(node('dt', label, 'text-xs text-gintly-text-secondary'), node('dd', value, 'mt-1 break-words text-sm font-semibold tabular-nums'));
    return wrapper;
}

function countDetail(snapshot, date) {
    if (!snapshot) return node('p', 'Sin conteo registrado', 'mt-4 text-sm text-gintly-text-secondary');
    const details = document.createElement('details');
    details.className = 'mt-4 rounded-xl bg-slate-50 p-3';
    const summary = node('summary', `Último conteo #${snapshot.id} · ${snapshot.label}`, 'min-h-11 cursor-pointer text-sm font-semibold');
    const grid = node('dl', '', 'mt-3 grid gap-4');
    grid.append(term('Cantidad física contada', snapshot.counted), term('Sistema al contar', snapshot.system),
        term('Diferencia histórica', snapshot.difference), term('Fecha del conteo', date(snapshot.date)));
    details.append(summary, grid);
    return details;
}

class InventoryView {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-inventory-filters]');
        this.sections = new Map([...root.querySelectorAll('[data-inventory-source]')].map((section) => [section.dataset.inventorySource, {
            element: section, page: 1, meta: null, controller: null, pending: false,
        }]));
        this.scope = null;
        this.scopeController = null;
        this.refreshing = false;
        this.lastRefresh = 0;
    }

    async init() {
        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (this.form.reportValidity()) void this.reload();
        });
        this.form.elements.branch_id.addEventListener('change', () => this.warehouseOptions());
        this.root.querySelector('[data-inventory-refresh]').addEventListener('click', () => void this.refresh());
        this.root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-source-retry], [data-source-prev], [data-source-next]');
            if (!button) return;
            const key = button.closest('[data-inventory-source]').dataset.inventorySource;
            const source = this.sections.get(key);
            if (source.pending) return;
            if (button.hasAttribute('data-source-prev')) source.page -= 1;
            if (button.hasAttribute('data-source-next')) source.page += 1;
            void this.load(key);
        });
        this.root.querySelector('[data-count-status]').addEventListener('change', () => {
            this.sections.get('counts').page = 1;
            void this.load('counts');
        });
        window.addEventListener('focus', () => { if (Date.now() - this.lastRefresh > 1000) void this.refresh(); });
        window.addEventListener('pageshow', (event) => { if (event.persisted) void this.refresh(); });
        document.addEventListener('visibilitychange', () => { if (!document.hidden && Date.now() - this.lastRefresh > 1000) void this.refresh(); });
        return this.refresh();
    }

    async refresh() {
        if (this.refreshing) return;
        this.refreshing = true;
        this.root.setAttribute('aria-busy', 'true');
        const button = this.root.querySelector('[data-inventory-refresh]');
        button.disabled = true;
        this.scopeController?.abort();
        this.scopeController = new AbortController();
        try {
            const selectedBranch = this.form.elements.branch_id.value;
            const selectedWarehouse = this.form.elements.warehouse_id.value;
            this.scope = await inventoryScope(this.scopeController.signal);
            this.root.querySelector('[data-inventory-fatal]').hidden = true;
            const context = this.scope.context;
            this.root.querySelector('[data-inventory-context]').textContent = context.role === 'ROL-03'
                ? `Sucursal #${context.branch.id} · Solo bodegas asignadas` : `${context.business.name} · Seguimiento del negocio`;
            this.root.querySelector('[data-branch-filter]').hidden = context.role === 'ROL-03';
            this.populate(this.form.elements.branch_id, this.scope.branches, 'Todas las sucursales');
            if (this.scope.branches.some((branch) => String(branch.id) === selectedBranch)) this.form.elements.branch_id.value = selectedBranch;
            this.warehouseOptions();
            if (this.scope.warehouses.some((warehouse) => String(warehouse.id) === selectedWarehouse
                && (!this.form.elements.branch_id.value || String(warehouse.branch_id) === this.form.elements.branch_id.value))) {
                this.form.elements.warehouse_id.value = selectedWarehouse;
            }
            const urls = Object.fromEntries([...this.root.querySelectorAll('[data-inventory-link]')].map((link) => [link.dataset.inventoryLink, link.href]));
            const keys = new Set(flattenedNavigation(authorizedNavigation(context, urls)).map((entry) => entry.key));
            this.root.querySelectorAll('[data-inventory-link]').forEach((link) => { link.hidden = !keys.has(link.dataset.inventoryLink); });
            const unassigned = context.role === 'ROL-03' && this.scope.warehouses.length === 0;
            this.root.querySelector('[data-inventory-unassigned]').hidden = !unassigned;
            this.form.hidden = unassigned;
            for (const source of this.sections.values()) source.element.hidden = unassigned;
            if (!unassigned) await this.reload();
        } catch (error) {
            this.fail(error);
            this.form.hidden = true;
            for (const source of this.sections.values()) source.element.hidden = true;
        } finally {
            this.lastRefresh = Date.now();
            this.refreshing = false;
            button.disabled = false;
            this.root.setAttribute('aria-busy', 'false');
        }
    }

    populate(select, records, prompt) {
        const empty = document.createElement('option'); empty.value = ''; empty.textContent = prompt;
        select.replaceChildren(empty, ...records.map((record) => {
            const option = document.createElement('option'); option.value = String(record.id); option.textContent = record.name; return option;
        }));
    }

    warehouseOptions() {
        const branchId = Number(this.form.elements.branch_id.value);
        this.populate(this.form.elements.warehouse_id, this.scope.warehouses.filter((warehouse) => !branchId || warehouse.branch_id === branchId), 'Todas las bodegas del alcance');
    }

    async reload() {
        for (const source of this.sections.values()) source.page = 1;
        await Promise.allSettled([...this.sections.keys()].map((key) => this.load(key)));
    }

    async load(key) {
        const source = this.sections.get(key);
        source.controller?.abort();
        const controller = new AbortController();
        source.controller = controller;
        source.pending = true;
        const section = source.element;
        section.setAttribute('aria-busy', 'true');
        section.querySelector('[data-source-loading]').hidden = false;
        for (const selector of ['[data-source-records]', '[data-source-error]', '[data-source-empty]', '[data-source-pagination]']) section.querySelector(selector).hidden = true;
        if (key === 'counts' && !this.scope.context.capabilities.includes('inventario.conteo')) {
            section.querySelector('[data-source-empty]').textContent = 'Tu cuenta no tiene capacidad para consultar conteos.';
            section.querySelector('[data-source-empty]').hidden = false;
            section.querySelector('[data-source-loading]').hidden = true;
            section.setAttribute('aria-busy', 'false'); source.pending = false; return;
        }
        const branchId = this.scope.context.role === 'ROL-03' ? null : Number(this.form.elements.branch_id.value) || null;
        const warehouseId = Number(this.form.elements.warehouse_id.value) || null;
        try {
            const query = key === 'stock' ? { search: this.form.elements.search.value.trim() || undefined } : {};
            if (key === 'counts') query.status = this.root.querySelector('[data-count-status]').value || undefined;
            const response = key === 'alerts'
                ? validatePage(await read(SOURCES[key], { branch_id: branchId, warehouse_id: warehouseId, per_page: 15, page: source.page }, controller.signal))
                : await inventoryPage(SOURCES[key], this.scope, { branchId, warehouseId, page: source.page, ...query }, controller.signal);
            if (controller.signal.aborted) return;
            const cards = response.data.map((record) => this.card(key, record));
            const output = section.querySelector('[data-source-records]');
            if (key === 'stock' && cards.length) output.replaceChildren(this.stockTable(cards));
            else output.replaceChildren(...cards);
            section.querySelector('[data-source-records]').hidden = cards.length === 0;
            section.querySelector('[data-source-empty]').textContent = key === 'alerts' ? 'Sin avisos de mínimo disponible para este alcance.' : 'No hay registros para este alcance.';
            section.querySelector('[data-source-empty]').hidden = cards.length > 0;
            source.meta = response.meta; source.page = response.meta.current_page;
            section.querySelector('[data-source-page-label]').textContent = `${source.meta.total} registros · Página ${source.page} de ${source.meta.last_page}`;
            section.querySelector('[data-source-pagination]').hidden = cards.length === 0;
            section.querySelector('[data-source-prev]').disabled = source.page <= 1;
            section.querySelector('[data-source-next]').disabled = source.page >= source.meta.last_page;
            section.querySelector('[data-consulted-at]').textContent = `Consultado: ${this.date(new Date().toISOString())}`;
        } catch (error) {
            if (controller.signal.aborted) return;
            if (error instanceof ApiError && error.status === 401) this.login();
            section.querySelector('[data-source-error-message]').textContent = errorMessage(error);
            section.querySelector('[data-source-error]').hidden = false;
        } finally {
            if (source.controller === controller) {
                source.pending = false; section.setAttribute('aria-busy', 'false'); section.querySelector('[data-source-loading]').hidden = true;
            }
        }
    }

    card(key, record) {
        const card = node('article', '', 'min-w-0 rounded-xl border border-slate-200 p-4');
        const values = node('dl', '', 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4');
        if (this.scope.context.role === 'ROL-03' && !this.scope.warehouses.some((warehouse) => warehouse.id === record.warehouse_id)) {
            throw new TypeError('El servidor devolvió datos de una bodega no asignada.');
        }
        if (key === 'stock') {
            const row = stockRecord(record);
            const tr = node('tr', '', 'mb-3 block rounded-xl border border-slate-200 xl:mb-0 xl:table-row xl:rounded-none xl:border-x-0');
            const product = node('th', '', 'block break-words p-3 text-left align-top xl:table-cell'); product.scope = 'row';
            product.append(node('span', row.product, 'font-semibold'), node('span', `${row.sku} · ${row.unit}`, 'mt-1 block text-xs font-normal text-gintly-text-secondary'));
            tr.append(product);
            for (const [label, value] of [['Bodega', row.warehouse], ['Existencia registrada', row.registered], ['Reservado', row.reserved], ['Disponible', row.available]]) {
                const cell = node('td', '', 'block break-words px-3 py-2 align-top xl:table-cell xl:py-3');
                cell.append(node('span', `${label}: `, 'text-xs text-gintly-text-secondary xl:hidden'), node('span', value, 'text-sm tabular-nums'));
                tr.append(cell);
            }
            const countCell = node('td', '', 'block px-3 pb-3 align-top xl:table-cell');
            countCell.append(countDetail(row.lastCount, (value) => this.date(value))); tr.append(countCell);
            return tr;
        } else if (key === 'counts') {
            const snapshot = countSnapshot(record);
            if (typeof record.product?.name !== 'string' || typeof record.product?.sku !== 'string' || typeof record.warehouse?.name !== 'string') throw new TypeError('El conteo no incluye sus relaciones de producto y bodega.');
            card.append(node('h3', `${record.product.name} · Conteo #${snapshot.id}`, 'break-words font-semibold'),
                node('p', `${record.product.sku} · ${productUnit(record.product)} · ${record.warehouse.name} · ${snapshot.label}`, 'mt-1 text-sm text-gintly-text-secondary'));
            values.append(term('Cantidad física', snapshot.counted), term('Sistema al contar', snapshot.system), term('Diferencia histórica', snapshot.difference), term('Fecha', this.date(snapshot.date)));
            values.classList.add('mt-4'); card.append(values);
            if (record.notes) card.append(node('p', `Observaciones: ${record.notes}`, 'mt-4 break-words text-sm'));
            card.append(node('p', 'Registro trazable. El operador no puede editar un conteo ya guardado.', 'mt-3 text-xs text-gintly-text-secondary'));
        } else {
            const alert = minimumAlert(record);
            card.append(node('h3', alert.product, 'break-words font-semibold'), node('p', `${alert.sku} · ${alert.unit ?? 'Unidad no incluida'} · ${alert.warehouse}`, 'mt-1 text-sm text-gintly-text-secondary'));
            values.append(term('Disponible actual', alert.available), term('Mínimo configurado', alert.minimum));
            values.classList.add('mt-4'); card.append(values, node('p', 'Aviso operativo de reposición; no es una anomalía ni una diferencia de conteo.', 'mt-3 text-xs text-gintly-text-secondary'));
        }
        return card;
    }

    stockTable(rows) {
        const table = node('table', '', 'block w-full text-sm xl:table xl:table-fixed');
        const caption = node('caption', 'Inventario registrado por producto y bodega', 'sr-only');
        const head = node('thead', '', 'hidden xl:table-header-group');
        const headings = node('tr', '');
        for (const [index, label] of ['Producto · SKU · Unidad', 'Bodega', 'Existencia', 'Reservado', 'Disponible', 'Último conteo'].entries()) {
            const th = node('th', label, `p-3 text-left text-xs font-semibold text-gintly-text-secondary ${index === 0 ? 'w-[28%]' : index === 5 ? 'w-[24%]' : ''}`);
            th.scope = 'col'; headings.append(th);
        }
        head.append(headings);
        const body = node('tbody', '', 'block xl:table-row-group'); body.append(...rows);
        table.append(caption, head, body); return table;
    }

    date(value) {
        if (value === null) return 'Fecha no incluida';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) throw new TypeError('Fecha de inventario inválida.');
        return new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium', timeStyle: 'short', timeZone: this.scope.context.business.timezone || 'America/Managua' }).format(date);
    }

    login() { window.location.assign(document.querySelector('meta[name="login-url"]').content); }
    fail(error) {
        if (error instanceof ApiError && error.status === 401) this.login();
        const fatal = this.root.querySelector('[data-inventory-fatal]'); fatal.textContent = errorMessage(error); fatal.hidden = false;
    }
}

export default function init() {
    const root = document.querySelector('[data-inventory-page]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new InventoryView(root).init();
}
