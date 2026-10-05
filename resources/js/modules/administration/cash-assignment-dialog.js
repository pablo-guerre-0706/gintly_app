import { ApiError } from '@/core/api-client';
import { clearFormErrors, showFormErrors, submitFormOnce } from '@/core/form-ui';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { createFocusTrap } from '@/core/focus-trap';
import { mutate, responseMessage } from '@/modules/organization/shared';
import { assignmentDirectory, getActiveCashAssignments } from '@/data/cash-assignments';
import { activeAssignment, AssignmentChangeError, createdCashAssignmentState, eligibleCashiers, replaceCashAssignment } from './cash-assignment-rules';

export class CashAssignmentDialog {
    constructor(root, onUpdated) {
        this.dialog = root.querySelector('[data-assignment-dialog]');
        this.form = this.dialog.querySelector('[data-assignment-form]');
        this.onUpdated = onUpdated; this.register = null; this.directory = null; this.current = null;
        this.request = null; this.opener = null; this.owner = Symbol('cash-assignment'); this.writing = false; this.loaded = false;
        this.focusTrap = createFocusTrap(this.dialog, { onEscape: () => { if (!this.writing) this.dialog.close(); } });
        this.dialog.querySelector('[data-assignment-close]').addEventListener('click', () => { if (!this.writing) this.dialog.close(); });
        this.dialog.addEventListener('cancel', (event) => { if (this.writing) event.preventDefault(); });
        this.dialog.addEventListener('close', () => { this.request?.abort(); this.focusTrap.deactivate(); unlockScroll(this.owner); this.opener?.focus(); this.opener = null; });
        this.dialog.querySelector('[data-assignment-retry]').addEventListener('click', () => { void this.load(); });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.save(false); });
        this.form.querySelector('[data-assignment-finish]').addEventListener('click', () => { void this.save(true); });
    }

    async open(register, opener, preferredUser = null, { created = false } = {}) {
        if (this.dialog.open) return;
        this.register = register; this.opener = opener; this.preferredUser = preferredUser; this.newlyCreated = created;
        this.form.reset(); clearFormErrors(this.form);
        this.dialog.querySelector('[data-assignment-context]').textContent = `${register.name} · Sucursal #${register.branch_id}`;
        lockScroll(this.owner); this.dialog.showModal(); this.focusTrap.activate(this.dialog.querySelector('[data-assignment-close]'));
        await this.load();
    }

    async load() {
        if (this.writing) return;
        this.request?.abort(); const controller = new AbortController(); this.request = controller;
        this.loaded = false; this.form.hidden = true; this.form.reset(); clearFormErrors(this.form);
        const message = this.dialog.querySelector('[data-assignment-state]'); message.textContent = 'Consultando asignación, sesión y cajeros de la sucursal…';
        this.dialog.querySelector('[data-assignment-retry]').hidden = true; this.dialog.setAttribute('aria-busy', 'true');
        try {
            this.directory = await assignmentDirectory(this.register, controller.signal);
            if (controller.signal.aborted) return;
            this.current = activeAssignment(this.directory.assignments, this.register.id);
            this.candidates = eligibleCashiers(this.directory.users, this.register, this.directory.assignments, this.directory.sessions);
            const open = this.directory.sessions.some((session) => session.cash_register_id === this.register.id);
            const select = this.form.elements.user_id;
            select.replaceChildren(new Option('Selecciona un cajero', ''), ...this.candidates.map((user) => new Option(user.name, String(user.id))));
            if (this.preferredUser && this.candidates.some((user) => user.id === this.preferredUser)) select.value = String(this.preferredUser);
            select.disabled = open || this.candidates.length === 0;
            const currentName = this.current?.user?.name ?? (this.current ? `Cajero #${this.current.user_id}` : null);
            message.textContent = open
                ? `Sesión abierta: ${currentName ?? 'la caja está en uso'}. No se puede cambiar ni finalizar la asignación hasta cerrar la sesión. Su evidencia permanece intacta.`
                : this.current ? `Asignación activa: ${currentName}.` : this.newlyCreated
                    ? 'La caja sí fue creada y todavía no tiene cajero. Confirma la asignación o hazlo después desde esta misma gestión.'
                    : 'La caja no tiene cajero asignado.';
            this.form.querySelector('[data-assignment-help]').textContent = this.candidates.length
                ? 'Solo cajeros activos de esta sucursal, sin otra caja asignada ni sesión abierta. Las Policies validan la operación final.'
                : 'No hay cajeros disponibles en esta sucursal. Crea un usuario ROL-03 con perfil cajero o finaliza su otra asignación cuando no tenga sesión abierta.';
            this.form.querySelector('[data-assignment-confirm-block]').hidden = !this.current || open;
            this.form.elements.confirmation.required = Boolean(this.current);
            this.form.querySelector('[data-assignment-submit]').textContent = this.current ? 'Cambiar cajero' : 'Asignar cajero';
            this.form.querySelector('[data-assignment-submit]').disabled = open || this.candidates.length === 0;
            this.form.querySelector('[data-assignment-finish]').hidden = !this.current;
            this.form.querySelector('[data-assignment-finish]').disabled = open;
            this.form.hidden = false; this.loaded = !open; this.dialog.querySelector('[data-assignment-retry]').hidden = false;
        } catch (error) {
            if (!controller.signal.aborted) { message.textContent = `${this.newlyCreated ? 'La caja sí fue creada. No se pudo confirmar su asignación. ' : ''}${responseMessage(error, 'No se pudo confirmar el estado de la asignación. Actualiza antes de operar.')}`; this.dialog.querySelector('[data-assignment-retry]').hidden = false; }
        } finally { if (this.request === controller) this.dialog.setAttribute('aria-busy', 'false'); }
    }

    async save(finishing) {
        if (this.writing || !this.loaded || (finishing && !this.current)) return;
        clearFormErrors(this.form);
        const userId = Number(this.form.elements.user_id.value);
        this.preferredUser = userId || this.preferredUser;
        if (!finishing && !this.candidates.some((user) => user.id === userId)) { showFormErrors(this.form, { user_id: ['Selecciona un cajero disponible de esta sucursal.'] }); return; }
        if (this.current && !this.form.elements.confirmation.checked) { showFormErrors(this.form, {}, 'Confirma la finalización de la asignación vigente.'); this.form.elements.confirmation.focus(); return; }
        this.writing = true; const button = this.form.querySelector(finishing ? '[data-assignment-finish]' : '[data-assignment-submit]');
        this.dialog.querySelector('[data-assignment-close]').disabled = true; this.dialog.querySelector('[data-assignment-retry]').disabled = true;
        const other = this.form.querySelector(finishing ? '[data-assignment-submit]' : '[data-assignment-finish]'); other.disabled = true;
        let success = false;
        await submitFormOnce(this.form, button, async () => {
            try {
            // Cada mutación renueva CSRF por separado; nunca repetir una cadena DELETE + POST.
            const finish = (id) => mutate('delete', `/cash-register-assignments/${id}`, null, { expectedStatus: 200 });
            if (finishing) {
                const ended = await finish(this.current.id);
                if (ended?.data?.id !== this.current.id || ended.data.active !== false || !ended.data.ended_at) throw new TypeError('No se confirmó la finalización. Actualiza antes de repetirla.');
                return;
            }
            await replaceCashAssignment({ assignment: this.current, registerId: this.register.id, userId, finish,
                assign: (payload) => mutate('post', '/cash-register-assignments', payload, { expectedStatus: 201 }) });
            } catch (error) {
                // mutate ya consumió la única recuperación CSRF. No repetir una cadena completa.
                if (error instanceof ApiError && error.status === 419) throw new Error(error.message, { cause: error });
                throw error;
            }
        }, {
            onSuccess: async () => { success = true; },
            onError: async (error) => {
                const original = error.cause ?? error;
                const summary = this.form.querySelector('[data-form-error-summary]');
                let creationState = '';
                if (this.newlyCreated && !finishing) {
                    try {
                        const assignments = await getActiveCashAssignments({ cash_register_id: this.register.id });
                        const state = createdCashAssignmentState(assignments, this.register.id, userId);
                        if (state.confirmed) { success = true; return; }
                        creationState = `${state.message} `;
                    } catch {
                        creationState = 'La caja sí fue creada, pero no se pudo confirmar si tiene cajero. Actualiza la asignación; no repitas el envío a ciegas. ';
                    }
                }
                const validation = original instanceof ApiError && original.status === 422
                    ? Object.values(original.errors ?? {}).flat().join(' ') : '';
                const uncertain = !(original instanceof ApiError) || original.status === 0 || original.status >= 500;
                const message = validation || (uncertain ? 'No se confirmó el resultado. Actualiza la asignación antes de repetirla.'
                    : responseMessage(original, 'Actualiza la asignación antes de repetirla.'));
                summary.textContent = `${creationState}${error instanceof AssignmentChangeError ? `${error.message} ` : ''}${message}`;
                summary.hidden = false;
                if (original instanceof ApiError && original.status === 422) showFormErrors(this.form, original.errors, summary.textContent);
                else summary.focus();
                this.loaded = false; button.disabled = true;
            },
        });
        this.writing = false; this.dialog.querySelector('[data-assignment-close]').disabled = false; this.dialog.querySelector('[data-assignment-retry]').disabled = false;
        if (success) { this.dialog.close(); await this.onUpdated(finishing ? 'Asignación finalizada. El historial se conserva.' : 'Cajero asignado correctamente.'); }
        else { this.form.querySelectorAll('button').forEach((control) => { control.disabled = true; }); }
    }
}
