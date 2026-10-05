import { api } from '@/core/api-client';
import { clearFormErrors, formErrorMessage, showFormErrors, submitFormOnce, trackUnsaved } from '@/core/form-ui';
import { getSessionContext } from '@/core/session-context';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { fetchPaginatedCollection, mutate } from '@/modules/organization/shared';
import { assignmentDirectory, assignmentCollection, getActiveCashAssignments } from '@/data/cash-assignments';
import { activeAssignment, canManageCashAssignments, eligibleCashiers } from './cash-assignment-rules';
import { CashAssignmentDialog } from './cash-assignment-dialog';

function element(tag, text, className = '') {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = className;
    return node;
}

class CashRegistersPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-register-form]');
        this.filters = root.querySelector('[data-register-filters]');
        this.page = 1;
        this.lastPage = 1;
        this.request = null;
        this.branches = new Map();
        this.records = new Map();
        this.pending = new Set();
        this.editing = null;
        this.clearDirty = null;
        this.confirmDialog = root.querySelector('[data-register-confirm]');
        this.confirmResolver = null;
        this.confirmOpener = null;
        this.confirmScrollOwner = Symbol('cash-register-confirm');
        this.assignments = []; this.openSessions = []; this.candidateRequest = null; this.assignmentDialog = null;
    }

    async init() {
        const context = await getSessionContext();
        if (!canManageCashAssignments(context)) {
            this.state('No tienes autorización para administrar cajas registradoras.', true);
            this.root.setAttribute('aria-busy', 'false');
            return;
        }
        this.assignmentDialog = new CashAssignmentDialog(this.root, async (message) => { this.notice(message); await this.load(); });
        this.root.querySelector('[data-register-create]').hidden = false;
        this.root.querySelector('[data-register-create]').addEventListener('click', () => { void this.openCreate(); });
        this.root.querySelector('[data-register-cancel]').addEventListener('click', () => { void this.closeForm(); });
        this.confirmDialog.querySelector('[data-register-confirm-cancel]').addEventListener('click', () => this.confirmDialog.close('cancel'));
        this.confirmDialog.querySelector('[data-register-confirm-accept]').addEventListener('click', () => this.confirmDialog.close('confirm'));
        this.confirmDialog.addEventListener('close', () => {
            unlockScroll(this.confirmScrollOwner);
            this.confirmOpener?.focus();
            this.confirmOpener = null;
            this.confirmResolver?.(this.confirmDialog.returnValue === 'confirm');
            this.confirmResolver = null;
        });
        this.filters.addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; void this.load(); });
        this.root.querySelector('[data-register-prev]').addEventListener('click', () => { this.page -= 1; void this.load(); });
        this.root.querySelector('[data-register-next]').addEventListener('click', () => { this.page += 1; void this.load(); });
        this.root.querySelector('[data-register-rows]').addEventListener('click', (event) => {
            const button = event.target.closest('button[data-register-action]');
            if (!button) return;
            const id = Number(button.closest('[data-register-id]')?.dataset.registerId);
            if (button.dataset.registerAction === 'edit') void this.openEdit(id);
            else if (button.dataset.registerAction === 'assignment') void this.assignmentDialog.open(this.records.get(id), button, this.preferredCashier);
            else void this.change(id, button.dataset.registerAction);
        });
        this.form.elements.branch_id.addEventListener('change', () => { void this.loadCandidates(); });
        this.form.elements.is_active.addEventListener('change', () => { void this.loadCandidates(); });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.save(); });
        try {
            const branches = await fetchPaginatedCollection('/branches', { per_page: 100, sort: 'name', direction: 'asc' }, { dispatchErrors: false });
            this.branches = new Map(branches.map((branch) => [branch.id, branch]));
            const filter = this.filters.elements.branch_id;
            const create = this.form.elements.branch_id;
            for (const branch of branches) {
                filter.add(new Option(branch.name, String(branch.id)));
                if (branch.is_active === true) create.add(new Option(branch.name, String(branch.id)));
            }
            await this.load();
            await this.openFromPersonal();
        } catch (error) { this.state(formErrorMessage(error), true, true); this.root.setAttribute('aria-busy', 'false'); }
    }

    state(text, error = false, retry = false) {
        const target = this.root.querySelector('[data-register-state]');
        target.replaceChildren(element('p', text));
        target.className = `p-5 text-sm ${error ? 'text-red-800' : 'text-gintly-text-secondary'}`;
        if (retry) {
            const button = element('button', 'Reintentar', 'mt-3 min-h-11 rounded-xl border border-red-300 px-4 font-semibold');
            button.type = 'button';
            button.addEventListener('click', () => this.initRetry());
            target.append(button);
        }
        target.hidden = false;
    }

    async initRetry() {
        if (this.branches.size) return this.load();
        try {
            const branches = await fetchPaginatedCollection('/branches', { per_page: 100, sort: 'name', direction: 'asc' }, { dispatchErrors: false });
            this.branches = new Map(branches.map((branch) => [branch.id, branch]));
            this.filters.elements.branch_id.replaceChildren(new Option('Todas', ''), ...branches.map((branch) => new Option(branch.name, String(branch.id))));
            this.form.elements.branch_id.replaceChildren(new Option('Selecciona una sucursal', ''), ...branches.filter((branch) => branch.is_active === true).map((branch) => new Option(branch.name, String(branch.id))));
            return this.load();
        } catch (error) { this.state(formErrorMessage(error), true, true); }
    }

    notice(text, error = false) {
        const target = this.root.querySelector('[data-register-notice]');
        target.className = `rounded-xl border p-4 text-sm font-semibold ${error ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`;
        target.textContent = text;
        target.hidden = false;
        target.focus();
    }

    row(record) {
        const row = document.createElement('article');
        row.dataset.registerId = String(record.id);
        row.className = 'min-w-0 space-y-4 rounded-2xl border border-slate-200 p-5';
        const branchName = record.branch?.name ?? this.branches.get(record.branch_id)?.name ?? `Sucursal #${record.branch_id}`;
        row.append(element('h3', record.name, 'break-words text-lg font-bold'), element('p', `${branchName} · ${record.is_active ? 'Activa' : 'Inactiva'}`, 'break-words text-sm text-slate-600'));
        const assignment = activeAssignment(this.assignments, record.id);
        row.append(element('p', assignment ? `Cajero asignado: ${assignment.user?.name ?? `Cajero #${assignment.user_id}`}` : 'Sin cajero asignado', 'break-words text-sm font-semibold'));
        if (this.openSessions.some((session) => session.cash_register_id === record.id)) {
            row.append(element('p', 'Sesión abierta: no se puede cambiar ni finalizar la asignación hasta cerrarla.', 'rounded-xl bg-amber-50 p-3 text-sm text-amber-950'));
        }
        const actions = document.createElement('div');
        actions.className = 'flex flex-wrap gap-2';
        for (const [action, label] of [['assignment', assignment ? 'Gestionar cajero' : 'Asignar cajero'], ['edit', 'Editar'], ['toggle', record.is_active ? 'Desactivar' : 'Activar'], ['delete', 'Dar de baja']]) {
            const button = element('button', label, 'min-h-11 rounded-xl border border-gintly-border px-3 text-sm font-semibold disabled:opacity-50');
            button.type = 'button';
            button.dataset.registerAction = action;
            actions.append(button);
        }
        row.append(actions);
        return row;
    }

    async load() {
        this.request?.abort();
        const controller = new AbortController();
        this.request = controller;
        this.root.setAttribute('aria-busy', 'true');
        this.state('Cargando cajas…');
        try {
            const [response, assignments, sessions] = await Promise.all([api.get('/cash-registers', {
                page: this.page, per_page: 20, sort: 'name', direction: 'asc',
                branch_id: this.filters.elements.branch_id.value || undefined,
                is_active: this.filters.elements.is_active.value || undefined,
            }, { signal: controller.signal, dispatchErrors: false }), getActiveCashAssignments({}, controller.signal), assignmentCollection('/cash-sessions', { status: 'abierta' }, controller.signal)]);
            if (controller.signal.aborted) return;
            if (!Array.isArray(response?.data) || !response?.meta || !response?.links) throw new TypeError('El servidor no devolvió cajas paginadas.');
            this.records = new Map(response.data.map((record) => [record.id, record]));
            this.assignments = assignments; this.openSessions = sessions;
            this.root.querySelector('[data-register-rows]').replaceChildren(...response.data.map((record) => this.row(record)));
            this.root.querySelector('[data-register-total]').textContent = `${response.meta.total} ${Number(response.meta.total) === 1 ? 'caja' : 'cajas'}`;
            this.page = Number(response.meta.current_page);
            this.lastPage = Number(response.meta.last_page);
            const pagination = this.root.querySelector('[data-register-pages]');
            pagination.hidden = this.lastPage <= 1;
            pagination.querySelector('[data-register-page]').textContent = `Página ${this.page} de ${this.lastPage}`;
            pagination.querySelector('[data-register-prev]').disabled = this.page <= 1;
            pagination.querySelector('[data-register-next]').disabled = this.page >= this.lastPage;
            if (response.data.length) this.root.querySelector('[data-register-state]').hidden = true;
            else this.state('No hay cajas para los filtros seleccionados.');
        } catch (error) {
            if (!controller.signal.aborted) this.state(formErrorMessage(error), true, true);
        } finally {
            if (this.request === controller) this.request = null;
            this.root.setAttribute('aria-busy', 'false');
        }
    }

    async openFromPersonal() {
        const userId = Number(new URLSearchParams(window.location.search).get('cashier'));
        if (!Number.isInteger(userId) || userId < 1) return;
        // El parámetro identifica un usuario, nunca una autorización ni una sucursal.
        try {
            const assignment = this.assignments.find((row) => row.user_id === userId);
            if (assignment) {
                const response = await api.get(`/cash-registers/${assignment.cash_register_id}`, {}, { dispatchErrors: false });
                if (response?.data?.id !== assignment.cash_register_id) throw new TypeError('Caja no confirmada.');
                await this.assignmentDialog.open(response.data, this.root.querySelector('[data-register-create]'));
            } else {
                const user = await api.get(`/users/${userId}/profiles`, {}, { dispatchErrors: false });
                if (user?.data?.id !== userId || user.data.role !== 'ROL-03' || !user.data.profiles?.includes('cajero')) throw new TypeError('El usuario no es un cajero autorizado.');
                this.filters.elements.branch_id.value = String(user.data.branch_id);
                await this.load(); this.notice(`Selecciona «Asignar cajero» en una caja de la sucursal de ${user.data.name}.`);
                this.preferredCashier = userId;
            }
        } catch (error) { this.notice(error.message || formErrorMessage(error), true); }
    }

    async loadCandidates() {
        this.candidateRequest?.abort(); const controller = new AbortController(); this.candidateRequest = controller;
        const select = this.form.elements.assignment_user_id; const help = this.root.querySelector('[data-register-cashier-help]');
        select.replaceChildren(new Option('Asignar después', '')); select.disabled = true;
        const branchId = Number(this.form.elements.branch_id.value);
        if (this.editing !== null || !this.branches.get(branchId)?.is_active || !this.form.elements.is_active.checked) { help.textContent = 'Selecciona una sucursal y crea una caja activa para ofrecer cajeros disponibles.'; return; }
        help.textContent = 'Consultando cajeros de la sucursal…';
        try {
            const register = { branch_id: branchId, is_active: true };
            const directory = await assignmentDirectory(register, controller.signal);
            if (controller.signal.aborted) return;
            const candidates = eligibleCashiers(directory.users, register, directory.assignments, directory.sessions);
            select.append(...candidates.map((user) => new Option(user.name, String(user.id)))); select.disabled = candidates.length === 0;
            help.textContent = candidates.length ? 'Opcional: después de guardar la caja confirmarás la asignación en su gestión. No son una operación conjunta.' : 'No hay cajeros disponibles en esta sucursal. Puedes crear la caja y asignar un cajero después desde esta misma gestión.';
        } catch (error) { if (!controller.signal.aborted) help.textContent = `No se pudieron consultar cajeros. Puedes asignar después. ${formErrorMessage(error)}`; }
    }

    confirmAction(message, label = 'Confirmar') {
        if (this.confirmDialog.open) return Promise.resolve(false);
        this.confirmOpener = document.activeElement;
        this.confirmDialog.querySelector('[data-register-confirm-message]').textContent = message;
        this.confirmDialog.querySelector('[data-register-confirm-accept]').textContent = label;
        this.confirmDialog.returnValue = 'cancel';
        lockScroll(this.confirmScrollOwner);
        this.confirmDialog.showModal();
        this.confirmDialog.querySelector('[data-register-confirm-cancel]').focus();
        return new Promise((resolve) => { this.confirmResolver = resolve; });
    }

    async openCreate() {
        if (this.clearDirty?.isDirty() && !await this.confirmAction('¿Descartar los cambios sin guardar para crear otra caja?', 'Descartar cambios')) return;
        this.editing = null;
        this.form.reset();
        this.form.elements.branch_id.replaceChildren(
            new Option('Selecciona una sucursal', ''),
            ...[...this.branches.values()].filter((branch) => branch.is_active === true).map((branch) => new Option(branch.name, String(branch.id))),
        );
        this.form.elements.branch_id.disabled = false;
        clearFormErrors(this.form);
        this.root.querySelector('[data-register-form-title]').textContent = 'Crear caja';
        this.root.querySelector('[data-register-form-region]').hidden = false;
        this.root.querySelector('[data-register-cashier-block]').hidden = false;
        void this.loadCandidates();
        this.clearDirty?.(); this.clearDirty = trackUnsaved(this.form);
        this.form.elements.name.focus();
    }

    async openEdit(id) {
        if (!this.records.has(id)) return;
        if (this.clearDirty?.isDirty() && !await this.confirmAction('¿Descartar los cambios sin guardar para editar otra caja?', 'Descartar cambios')) return;
        this.state('Cargando caja…');
        try {
            const response = await api.get(`/cash-registers/${id}`, {}, { dispatchErrors: false });
            const record = response?.data;
            if (record?.id !== id) throw new TypeError('El servidor no devolvió la caja solicitada.');
            this.editing = id;
            this.form.elements.name.value = record.name;
            this.form.elements.branch_id.replaceChildren(new Option(record.branch?.name ?? `Sucursal #${record.branch_id}`, String(record.branch_id)));
            this.form.elements.branch_id.disabled = true;
            this.form.elements.is_active.checked = record.is_active === true;
            clearFormErrors(this.form);
            this.root.querySelector('[data-register-form-title]').textContent = `Editar ${record.name}`;
            this.root.querySelector('[data-register-form-region]').hidden = false;
            this.root.querySelector('[data-register-cashier-block]').hidden = true;
            this.root.querySelector('[data-register-state]').hidden = true;
            this.clearDirty?.(); this.clearDirty = trackUnsaved(this.form);
            this.form.elements.name.focus();
        } catch (error) { this.state(formErrorMessage(error), true, true); }
    }

    async closeForm() {
        if (this.form.dataset.submitting === 'true') return;
        if (this.clearDirty?.isDirty() && !await this.confirmAction('¿Salir del formulario? Se perderán los cambios sin guardar.', 'Descartar cambios')) return;
        this.clearDirty?.(); this.clearDirty = null;
        this.candidateRequest?.abort();
        this.root.querySelector('[data-register-form-region]').hidden = true;
        this.root.querySelector('[data-register-create]').focus();
    }

    async save() {
        const requestedCashier = this.editing === null ? Number(this.form.elements.assignment_user_id.value) || null : null;
        const data = { name: this.form.elements.name.value.trim(), is_active: this.form.elements.is_active.checked };
        if (this.editing === null) data.branch_id = Number(this.form.elements.branch_id.value);
        const errors = {};
        if (!data.name) errors.name = ['El nombre es obligatorio.'];
        if (this.editing === null && !this.branches.get(data.branch_id)?.is_active) errors.branch_id = ['Selecciona una sucursal activa.'];
        if (Object.keys(errors).length) { showFormErrors(this.form, errors); return; }
        await submitFormOnce(this.form, this.form.querySelector('[data-register-save]'), () => this.editing === null
            ? api.post('/cash-registers', data, { dispatchErrors: false, expectedStatus: 201 })
            : api.patch(`/cash-registers/${this.editing}`, data, { dispatchErrors: false, expectedStatus: 200 }), {
            onSuccess: async (response) => {
                if (!Number.isInteger(response?.data?.id)) throw new TypeError('El servidor no confirmó la caja.');
                this.clearDirty?.(); this.clearDirty = null;
                this.root.querySelector('[data-register-form-region]').hidden = true;
                this.notice('Caja guardada correctamente.');
                await this.load();
                if (requestedCashier) await this.assignmentDialog.open(response.data, this.root.querySelector('[data-register-create]'), requestedCashier, { created: true });
            },
        });
    }

    async change(id, action) {
        const record = this.records.get(id);
        if (!record || this.pending.has(id)) return;
        if (action === 'delete' && !await this.confirmAction(`¿Dar de baja la caja «${record.name}»? Las sesiones históricas permanecen como evidencia.`, 'Dar de baja')) return;
        if (action === 'toggle' && record.is_active && !await this.confirmAction(`¿Desactivar la caja «${record.name}»?`, 'Desactivar')) return;
        const row = [...this.root.querySelectorAll('[data-register-id]')].find((item) => Number(item.dataset.registerId) === id);
        this.pending.add(id);
        row?.setAttribute('aria-busy', 'true');
        row?.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        try {
            if (action === 'delete') await mutate('delete', `/cash-registers/${id}`, null, { expectedStatus: 204 });
            else {
                const response = await mutate('patch', `/cash-registers/${id}`, { is_active: !record.is_active }, { expectedStatus: 200 });
                if (response?.data?.id !== id) throw new TypeError('El servidor no confirmó el cambio.');
            }
            this.notice(action === 'delete' ? 'Caja dada de baja.' : 'Estado de caja actualizado.');
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
    const root = document.querySelector('[data-admin-cash-registers]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new CashRegistersPage(root).init();
}
