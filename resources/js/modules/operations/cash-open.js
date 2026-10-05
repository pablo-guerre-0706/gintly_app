import { ApiError } from '@/core/api-client';
import { CashDenominationGrid } from './cash-denomination-grid';
import { cashierContext, cashBusy, cashError, cashFieldErrors, cashNotice, cashPost, cashUrls, currentCashSession } from './cash-shared';
import { OpeningVerificationError, openVerifiedSession } from './cash-opening-flow';
import { getOwnCashAssignment } from '@/data/cash-assignments';

class CashOpening {
    constructor(root) { this.root = root; this.form = root.querySelector('[data-cash-form]'); this.pending = false; this.blocked = false; this.grid = null; this.context = null; }

    async load() {
        if (this.pending) return;
        this.pending = true;
        this.root.setAttribute('aria-busy', 'true');
        this.root.querySelector('[data-cash-loading]').hidden = false;
        this.form.hidden = true;
        try {
            this.context = await cashierContext('caja.abrir');
            const session = await currentCashSession(this.context);
            if (session) { this.empty('Ya tienes una sesión abierta. Continúa desde Mi caja.'); return; }
            this.assignment = await getOwnCashAssignment(this.context);
            if (!this.assignment || !this.assignment.cash_register.is_active) { this.empty('No tienes una caja activa asignada en tu sucursal. Solicita al administrador asignártela antes de abrir.'); return; }
            this.form.elements.cash_register_id.value = this.assignment.cash_register.name;
            this.root.querySelector('[data-cash-branch]').textContent = `Sucursal asignada #${this.context.branch.id}`;
            this.grid = new CashDenominationGrid(this.form);
            this.form.hidden = false;
        } catch (error) { cashNotice(this.root, cashError(error), true); this.empty('No fue posible preparar la apertura. Actualiza la página para reintentar.'); }
        finally { this.pending = false; this.root.querySelector('[data-cash-loading]').hidden = true; this.root.setAttribute('aria-busy', 'false'); }
    }

    empty(message) { this.root.querySelector('[data-cash-empty-text]').textContent = message; this.root.querySelector('[data-cash-empty]').hidden = false; }

    confirmed(session) {
        // El Resource y GET current ya confirmaron la persistencia. No queda fondo residual.
        this.form.reset(); this.grid?.reset(); this.form.hidden = true;
        cashNotice(this.root, `El servidor confirma la sesión abierta #${session.id}. Continuando a Mi caja…`);
        window.location.assign(cashUrls().summary);
    }

    async verify() {
        if (this.pending) return;
        this.pending = true; cashBusy(this.form, true, 'Verificando sesión…');
        try {
            const session = await currentCashSession(this.context);
            if (session) { this.confirmed(session); return; }
            this.blocked = false; this.root.querySelector('[data-cash-verify]').hidden = true;
            cashNotice(this.root, 'El servidor confirma que no hay sesión abierta. Puedes volver a enviar los importes conservados.');
        } catch (error) { cashNotice(this.root, `${cashError(error)} La apertura permanece bloqueada hasta verificar la sesión.`, true); }
        finally { this.pending = false; cashBusy(this.form, false); this.form.querySelector('[type="submit"]').disabled = this.blocked; }
    }

    async submit() {
        if (this.pending || this.blocked || !this.grid) return;
        cashFieldErrors(this.form);
        const register = this.form.elements.namedItem('cash_register_id');
        if (!this.assignment) { register.focus(); cashNotice(this.root, 'El administrador debe asignarte una caja.', true); return; }
        let counted;
        try { counted = this.grid.payload(); }
        catch (error) { cashNotice(this.root, error.message, true); return; }
        const payload = { cash_register_id: this.assignment.cash_register_id, opening_amount: counted.counted_amount, opening_amount_usd: counted.counted_amount_usd };
        this.pending = true; cashBusy(this.form, true, 'Abriendo caja…');
        try {
            const result = await openVerifiedSession({
                current: () => currentCashSession(this.context),
                post: (data) => cashPost('/cash-sessions', data, 201), payload,
            });
            this.confirmed(result.session);
        } catch (error) {
            this.blocked = error instanceof OpeningVerificationError;
            this.root.querySelector('[data-cash-verify]').hidden = !this.blocked;
            const description = this.blocked || (error instanceof ApiError && error.status === 403) ? error.message : cashError(error);
            const status = error instanceof ApiError ? `HTTP ${error.status}${error.code ? ` · ${error.code}` : ''}: ` : '';
            cashNotice(this.root, `${status}${description}`, true);
            if (error instanceof ApiError && error.status === 422) cashFieldErrors(this.form, error.errors);
        } finally { this.pending = false; cashBusy(this.form, false); this.form.querySelector('[type="submit"]').disabled = this.blocked; }
    }
}

export default function init() {
    const root = document.querySelector('[data-cash-open]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    const page = new CashOpening(root);
    page.form.addEventListener('submit', (event) => { event.preventDefault(); void page.submit(); });
    root.querySelector('[data-cash-verify]').addEventListener('click', () => { void page.verify(); });
    void page.load();
}
