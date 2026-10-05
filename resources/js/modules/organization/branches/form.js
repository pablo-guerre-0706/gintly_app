import { api, ApiError } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { clearFieldErrors, fetchPaginatedCollection, mutate, responseMessage, setButtonBusy, showFieldErrors } from '../shared';

function option(value, label) {
    const node = document.createElement('option');
    node.value = String(value);
    node.textContent = label;
    return node;
}

class BranchForm {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-branch-form]');
        this.mode = root.dataset.mode;
        this.branchId = Number(root.dataset.branchId);
        this.initial = null;
        this.loading = false;
        this.saving = false;
    }

    init() {
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.save(); });
        this.root.querySelector('[data-branch-retry]').addEventListener('click', () => void this.load());
        return this.load();
    }

    showFatal(error) {
        this.root.querySelector('[data-branch-loading]').hidden = true;
        this.form.hidden = true;
        this.root.querySelector('[data-branch-fatal-message]').textContent = error instanceof ApiError
            ? responseMessage(error) : (error.message || 'No fue posible cargar la sucursal o los responsables.');
        this.root.querySelector('[data-branch-fatal]').hidden = false;
    }

    async load() {
        if (this.loading) return;
        this.loading = true;
        this.root.querySelector('[data-branch-loading]').hidden = false;
        this.root.querySelector('[data-branch-fatal]').hidden = true;
        this.form.hidden = true;
        try {
            const context = await getSessionContext();
            if (!['ROL-01', 'ROL-02'].includes(context.role) || !context.capabilities.includes('sucursales.gestionar')) {
                throw new Error('No tienes autorización para administrar sucursales.');
            }
            if (this.mode === 'edit' && (!Number.isInteger(this.branchId) || this.branchId < 1)) {
                throw new Error('Identificador de sucursal no válido.');
            }
            const [users, response] = await Promise.all([
                fetchPaginatedCollection(this.root.dataset.usersEndpoint, { per_page: 100, is_active: true, sort: 'name', direction: 'asc' }, { dispatchErrors: false }),
                this.mode === 'edit'
                    ? api.get(`${this.root.dataset.branchesEndpoint}/${this.branchId}`, {}, { dispatchErrors: false })
                    : Promise.resolve(null),
            ]);
            const active = users.filter((user) => user.is_active === true && Number.isInteger(user.id));
            if (!active.length) throw new Error('No existen usuarios activos que puedan ser responsables de la sucursal.');
            const manager = this.form.elements.manager_user_id;
            manager.replaceChildren(option('', 'Selecciona un usuario activo'), ...active.map((user) => option(user.id, user.name)));
            if (this.mode === 'edit') {
                const branch = response?.data;
                if (!Number.isInteger(branch?.id) || branch.id !== this.branchId) throw new Error('El servidor no devolvió la sucursal solicitada.');
                this.initial = {
                    name: branch.name,
                    address: branch.address,
                    manager_user_id: String(branch.manager_user_id ?? ''),
                    opened_at: branch.opened_at ?? '',
                    is_active: branch.is_active === true,
                };
                this.form.elements.name.value = this.initial.name;
                this.form.elements.address.value = this.initial.address;
                manager.value = active.some((user) => String(user.id) === this.initial.manager_user_id) ? this.initial.manager_user_id : '';
                this.form.elements.opened_at.value = this.initial.opened_at;
                this.form.elements.is_active.checked = this.initial.is_active;
                if (!manager.value) this.root.querySelector('[data-branch-error]').textContent = 'El responsable actual ya no está activo. Selecciona un responsable activo antes de guardar.';
                this.root.querySelector('[data-branch-error]').hidden = Boolean(manager.value);
            }
            this.root.querySelector('[data-branch-loading]').hidden = true;
            this.form.hidden = false;
            this.form.elements.name.focus();
        } catch (error) {
            this.showFatal(error);
        } finally {
            this.loading = false;
        }
    }

    values() {
        return {
            name: this.form.elements.name.value.trim(),
            address: this.form.elements.address.value.trim(),
            manager_user_id: Number(this.form.elements.manager_user_id.value),
            opened_at: this.form.elements.opened_at.value,
            is_active: this.form.elements.is_active.checked,
        };
    }

    async save() {
        if (this.saving) return;
        clearFieldErrors(this.form);
        const errorBox = this.root.querySelector('[data-branch-error]');
        errorBox.hidden = true;
        if (!this.form.reportValidity()) return;
        const values = this.values();
        const payload = this.mode === 'edit'
            ? Object.fromEntries(Object.entries(values).filter(([key, value]) => String(value) !== String(this.initial[key])))
            : values;
        if (this.mode === 'edit' && Object.keys(payload).length === 0) {
            errorBox.textContent = 'No hay cambios que guardar.';
            errorBox.hidden = false;
            return;
        }
        const button = this.root.querySelector('[data-branch-submit]');
        this.saving = true;
        this.form.setAttribute('aria-busy', 'true');
        setButtonBusy(button, true, this.mode === 'create' ? 'Creando sucursal…' : 'Guardando cambios…');
        try {
            const response = this.mode === 'create'
                ? await mutate('post', this.root.dataset.branchesEndpoint, payload, { expectedStatus: 201 })
                : await mutate('patch', `${this.root.dataset.branchesEndpoint}/${this.branchId}`, payload, { expectedStatus: 200 });
            if (!Number.isInteger(response?.data?.id)) throw new Error('El servidor no confirmó la sucursal. Verifica el directorio antes de reintentar.');
            const query = this.mode === 'create' ? 'created=1' : 'updated=1';
            window.location.assign(`${this.root.dataset.indexUrl}?${query}`);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) showFieldErrors(this.form, error.errors);
            errorBox.textContent = responseMessage(error, 'No fue posible guardar la sucursal. Verifica el directorio antes de reintentar.');
            errorBox.hidden = false;
            if (!(error instanceof ApiError && error.status === 422)) errorBox.focus();
        } finally {
            this.saving = false;
            this.form.setAttribute('aria-busy', 'false');
            setButtonBusy(button, false);
        }
    }
}

export default function init() {
    const root = document.querySelector('[data-branch-form-root]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new BranchForm(root).init();
}
