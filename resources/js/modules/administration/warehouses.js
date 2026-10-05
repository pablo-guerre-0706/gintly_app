import { api } from '@/core/api-client';
import { clearFormErrors, formErrorMessage, showFormErrors, submitFormOnce, trackUnsaved } from '@/core/form-ui';
import { getSessionContext } from '@/core/session-context';
import { fetchPaginatedCollection, mutate } from '@/modules/organization/shared';

function node(tag, text, className = '') {
    const element = document.createElement(tag);
    element.textContent = text;
    element.className = className;
    return element;
}

class WarehouseAdmin {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-warehouse-form]');
        this.page = 1;
        this.lastPage = 1;
        this.request = null;
        this.pending = new Set();
        this.records = new Map();
        this.branches = new Map();
        this.editing = null;
        this.clearDirty = null;
    }

    async init() {
        const context = await getSessionContext();
        if (!['ROL-01', 'ROL-02'].includes(context.role) || !context.capabilities.includes('bodegas.gestionar')) {
            this.state('No tienes autorización para administrar bodegas.', true);
            return;
        }
        this.root.querySelector('[data-warehouse-create]').hidden = false;
        this.root.querySelector('[data-warehouse-create]').addEventListener('click', () => this.openCreate());
        this.root.querySelector('[data-warehouse-cancel]').addEventListener('click', () => this.closeForm());
        this.root.querySelector('[data-warehouse-filters]').addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; void this.load(); });
        this.root.querySelector('[data-warehouse-prev]').addEventListener('click', () => { this.page -= 1; void this.load(); });
        this.root.querySelector('[data-warehouse-next]').addEventListener('click', () => { this.page += 1; void this.load(); });
        this.root.querySelector('[data-warehouse-rows]').addEventListener('click', (event) => {
            const button = event.target.closest('button[data-warehouse-action]');
            if (!button) return;
            const id = Number(button.closest('[data-warehouse-id]')?.dataset.warehouseId);
            if (button.dataset.warehouseAction === 'edit') void this.openEdit(id);
            else void this.change(id, button.dataset.warehouseAction);
        });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.save(); });
        try {
            const branches = await fetchPaginatedCollection('/branches', { per_page: 100, sort: 'name', direction: 'asc' }, { dispatchErrors: false });
            this.branches = new Map(branches.map((branch) => [branch.id, branch]));
            for (const select of [this.form.elements.branch_id, this.root.querySelector('[name="branch_id"]:not([required])')]) {
                for (const branch of branches) {
                    const option = new Option(branch.name, String(branch.id));
                    if (select === this.form.elements.branch_id && branch.is_active !== true) option.disabled = true;
                    select.add(option);
                }
            }
            await this.load();
        } catch (error) { this.state(formErrorMessage(error), true, true); }
    }

    state(text, error = false, retry = false) {
        const target = this.root.querySelector('[data-warehouse-state]');
        target.replaceChildren(node('p', text));
        target.className = `p-5 text-sm ${error ? 'text-red-800' : 'text-gintly-text-secondary'}`;
        if (retry) {
            const button = node('button', 'Reintentar', 'mt-3 min-h-11 rounded-xl border border-red-300 px-4 font-semibold');
            button.type = 'button';
            button.addEventListener('click', () => this.load());
            target.append(button);
        }
        target.hidden = false;
    }

    notice(text, error = false) {
        const target = this.root.querySelector('[data-warehouse-notice]');
        target.textContent = text;
        target.className = `rounded-xl border p-4 text-sm font-semibold ${error ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`;
        target.hidden = false;
        target.focus();
    }

    row(record) {
        const tr = document.createElement('tr');
        tr.dataset.warehouseId = String(record.id);
        const values = [record.name, this.branches.get(record.branch_id)?.name ?? `Sucursal #${record.branch_id}`, record.is_default ? 'Sí' : 'No', record.is_active ? 'Activa' : 'Inactiva'];
        for (const value of values) tr.append(node('td', value, 'p-4 align-middle'));
        const actions = document.createElement('td');
        actions.className = 'p-4';
        const wrap = document.createElement('div');
        wrap.className = 'flex flex-wrap gap-2';
        for (const [action, label] of [['edit', 'Editar'], ['toggle', record.is_active ? 'Desactivar' : 'Activar'], ['default', 'Predeterminada'], ['delete', 'Eliminar']]) {
            if (action === 'default' && record.is_default) continue;
            const button = node('button', label, 'min-h-11 rounded-xl border border-slate-300 px-3 text-sm font-semibold disabled:opacity-50');
            button.type = 'button'; button.dataset.warehouseAction = action; wrap.append(button);
        }
        actions.append(wrap); tr.append(actions); return tr;
    }

    async load() {
        this.request?.abort();
        const controller = new AbortController();
        this.request = controller;
        this.state('Cargando bodegas…');
        const filters = this.root.querySelector('[data-warehouse-filters]').elements;
        try {
            const response = await api.get('/warehouses', {
                page: this.page, per_page: 20, sort: 'name', direction: 'asc',
                branch_id: filters.branch_id.value || undefined,
                is_active: filters.is_active.value || undefined,
            }, { signal: controller.signal, dispatchErrors: false });
            if (controller.signal.aborted) return;
            if (!Array.isArray(response?.data) || !response?.meta || !response?.links) throw new TypeError('El servidor no devolvió bodegas paginadas.');
            this.records = new Map(response.data.map((record) => [record.id, record]));
            this.root.querySelector('[data-warehouse-rows]').replaceChildren(...response.data.map((record) => this.row(record)));
            this.root.querySelector('[data-warehouse-total]').textContent = `${response.meta.total} ${Number(response.meta.total) === 1 ? 'bodega' : 'bodegas'}`;
            this.page = Number(response.meta.current_page);
            this.lastPage = Number(response.meta.last_page);
            const pagination = this.root.querySelector('[data-warehouse-pages]');
            pagination.hidden = this.lastPage <= 1;
            pagination.querySelector('[data-warehouse-page]').textContent = `Página ${this.page} de ${this.lastPage}`;
            pagination.querySelector('[data-warehouse-prev]').disabled = this.page <= 1;
            pagination.querySelector('[data-warehouse-next]').disabled = this.page >= this.lastPage;
            const state = this.root.querySelector('[data-warehouse-state]');
            if (response.data.length) state.hidden = true;
            else this.state('No hay bodegas para los filtros seleccionados.');
        } catch (error) {
            if (controller.signal.aborted) return;
            this.state(formErrorMessage(error), true, true);
        } finally { if (this.request === controller) this.request = null; }
    }

    openCreate() {
        this.editing = null;
        this.form.reset();
        this.form.elements.branch_id.disabled = false;
        clearFormErrors(this.form);
        this.root.querySelector('[data-warehouse-form-title]').textContent = 'Crear bodega';
        this.root.querySelector('[data-warehouse-form-region]').hidden = false;
        this.clearDirty?.(); this.clearDirty = trackUnsaved(this.form);
        this.form.elements.name.focus();
    }

    async openEdit(id) {
        if (!this.records.has(id)) return;
        this.state('Cargando bodega…');
        try {
            const response = await api.get(`/warehouses/${id}`, {}, { dispatchErrors: false });
            const record = response?.data;
            if (record?.id !== id) throw new TypeError('No se recibió la bodega solicitada.');
            this.editing = id;
            this.form.elements.name.value = record.name;
            this.form.elements.branch_id.value = String(record.branch_id);
            this.form.elements.branch_id.disabled = true;
            this.form.elements.is_default.checked = record.is_default === true;
            this.form.elements.is_active.checked = record.is_active === true;
            clearFormErrors(this.form);
            this.root.querySelector('[data-warehouse-form-title]').textContent = `Editar ${record.name}`;
            this.root.querySelector('[data-warehouse-form-region]').hidden = false;
            this.root.querySelector('[data-warehouse-state]').hidden = true;
            this.clearDirty?.(); this.clearDirty = trackUnsaved(this.form);
            this.form.elements.name.focus();
        } catch (error) { this.state(formErrorMessage(error), true, true); }
    }

    closeForm() {
        if (this.clearDirty?.isDirty() && !window.confirm('¿Salir del formulario? Se perderán los cambios sin guardar.')) return;
        this.clearDirty?.(); this.clearDirty = null;
        this.root.querySelector('[data-warehouse-form-region]').hidden = true;
        this.root.querySelector('[data-warehouse-create]').focus();
    }

    async save() {
        const form = this.form;
        const data = { name: form.elements.name.value.trim(), is_default: form.elements.is_default.checked, is_active: form.elements.is_active.checked };
        if (this.editing === null) data.branch_id = Number(form.elements.branch_id.value);
        const errors = {};
        if (!data.name) errors.name = ['El nombre es obligatorio.'];
        if (this.editing === null && (!Number.isInteger(data.branch_id) || !this.branches.get(data.branch_id)?.is_active)) errors.branch_id = ['Selecciona una sucursal activa.'];
        if (Object.keys(errors).length) { showFormErrors(form, errors); return; }
        await submitFormOnce(form, form.querySelector('[data-warehouse-save]'), () => this.editing === null
            ? api.post('/warehouses', data, { dispatchErrors: false, expectedStatus: 201 })
            : api.patch(`/warehouses/${this.editing}`, data, { dispatchErrors: false, expectedStatus: 200 }), {
            onSuccess: async (response) => {
                if (!Number.isInteger(response?.data?.id)) throw new TypeError('El servidor no confirmó la bodega.');
                this.clearDirty?.(); this.clearDirty = null;
                this.root.querySelector('[data-warehouse-form-region]').hidden = true;
                this.notice('Bodega guardada correctamente.');
                await this.load();
            },
        });
    }

    async change(id, action) {
        const record = this.records.get(id);
        if (!record || this.pending.has(id)) return;
        if (action === 'delete' && !window.confirm(`¿Dar de baja la bodega «${record.name}»? El servidor rechazará la baja si tiene dependencias.`)) return;
        if (action === 'toggle' && record.is_active && !window.confirm(`¿Desactivar la bodega «${record.name}»?`)) return;
        if (action === 'default' && !window.confirm(`¿Hacer «${record.name}» la bodega predeterminada de su sucursal?`)) return;
        const row = [...this.root.querySelectorAll('[data-warehouse-id]')].find((element) => Number(element.dataset.warehouseId) === id);
        this.pending.add(id);
        row?.setAttribute('aria-busy', 'true');
        row?.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        try {
            if (action === 'delete') await mutate('delete', `/warehouses/${id}`, null, { expectedStatus: 204 });
            else {
                const patch = action === 'toggle' ? { is_active: !record.is_active } : { is_default: true };
                const response = await mutate('patch', `/warehouses/${id}`, patch, { expectedStatus: 200 });
                if (response?.data?.id !== id) throw new TypeError('El servidor no confirmó el cambio.');
            }
            this.notice(action === 'delete' ? 'Bodega dada de baja.' : 'Bodega actualizada.');
            await this.load();
        } catch (error) { this.notice(formErrorMessage(error), true); }
        finally {
            this.pending.delete(id);
            row?.removeAttribute('aria-busy');
            row?.querySelectorAll('button').forEach((button) => { button.disabled = false; });
        }
    }
}

export default function init() {
    const root = document.querySelector('[data-warehouses-admin]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new WarehouseAdmin(root).init();
}
