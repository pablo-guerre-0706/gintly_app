import { formatDateTime } from '@/dashboard/formatters';
import { cashierContext, cashError, cashLabel, cashNotice, currentCashSession } from './cash-shared';
import { getOwnCashAssignment } from '@/data/cash-assignments';

class CashSummary {
    constructor(root) { this.root = root; this.pending = false; }

    async load() {
        if (this.pending) return;
        this.pending = true;
        const root = this.root;
        root.setAttribute('aria-busy', 'true');
        root.querySelector('[data-cash-loading]').hidden = false;
        root.querySelector('[data-cash-ready]').hidden = true;
        root.querySelector('[data-cash-retry]').hidden = true;
        try {
            const context = await cashierContext();
            const [session, assignment] = await Promise.all([currentCashSession(context), getOwnCashAssignment(context)]);
            if (session && assignment && session.cash_register_id !== assignment.cash_register_id) throw new TypeError('La sesión no corresponde a tu caja asignada.');
            root.querySelector('[data-cash-status]').textContent = session ? `Abierta · #${session.id}` : 'Sin sesión abierta';
            root.querySelector('[data-cash-register]').textContent = session ? `${session.cash_register?.name ?? `Caja #${session.cash_register_id}`} · Sucursal #${context.branch.id}`
                : assignment ? `${assignment.cash_register.name} · Sucursal #${context.branch.id}` : `Sin caja asignada · Sucursal #${context.branch.id}`;
            root.querySelector('[data-cash-opening]').textContent = session ? `${cashLabel(session.opening_amount)} / ${cashLabel(session.opening_amount_usd, 'USD')}` : '—';
            root.querySelector('[data-cash-opened]').textContent = session ? formatDateTime(session.opened_at, context.business.timezone) : '—';
            root.querySelector('[data-cash-open-link]').hidden = Boolean(session) || !assignment || !assignment.cash_register.is_active || !context.capabilities.includes('caja.abrir');
            root.querySelector('[data-cash-assignment-empty]').hidden = Boolean(assignment);
            root.querySelector('[data-cash-movements-link]').hidden = !session || !context.capabilities.includes('caja.movimiento.crear');
            root.querySelector('[data-cash-count-link]').hidden = !session || !context.capabilities.includes('caja.movimiento.crear');
            root.querySelector('[data-cash-close-link]').hidden = !session || !context.capabilities.includes('caja.cerrar');
            root.querySelector('[data-cash-blind-note]').hidden = !session;
            root.querySelector('[data-cash-loading]').hidden = true;
            root.querySelector('[data-cash-ready]').hidden = false;
        } catch (error) {
            root.querySelector('[data-cash-loading]').hidden = true;
            root.querySelector('[data-cash-retry]').hidden = false;
            cashNotice(root, cashError(error), true);
        } finally { root.setAttribute('aria-busy', 'false'); this.pending = false; }
    }
}

export default async function init() {
    const root = document.querySelector('[data-cash-summary]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    const controller = new CashSummary(root);
    root.querySelector('[data-cash-retry]').addEventListener('click', () => { void controller.load(); });
    await controller.load();
}
