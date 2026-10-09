import { STATES, PERIODS, FEATURES, priceText } from './contracts.js';
const element = (tag, text, classes = '') => { const node = document.createElement(tag); node.textContent = text; node.className = classes; return node; };
export function billingView(root) {
    const find = hook => root.querySelector(`[data-${hook}]`);
    function notice(message, focus = false) { const node = find('billing-notice'); node.textContent = message; node.hidden = !message; if (focus) node.focus(); }
    function renderState(value, plans, timezone) {
        find('billing-state').hidden = false;
        const demo = value.access_source === 'demo';
        find('subscription-status').textContent = demo ? 'Demostración temporal' : STATES[value.status];
        find('subscription-access').textContent = demo ? 'Acceso de evaluación · sin pago' : value.grants_access ? 'Acceso comercial vigente' : 'Sin acceso operativo confirmado';
        find('billing-enter').hidden = !value.grants_access;
        const name = key => plans.find(plan => plan.key === key)?.name ?? key;
        const date = iso => iso ? new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(new Date(iso)) : 'No informada';
        const details = find('subscription-detail'); details.replaceChildren();
        const fields = demo
            ? [['Plan de evaluación', name(value.demo_access.plan_key)], ['Demostración hasta', date(value.demo_access.expires_at)], ['Condición', 'Concesión administrativa revocable. No se registró pago ni contratación.']]
            : [['Plan', value.plan_key ? name(value.plan_key) : 'No contratado'], ['Periodicidad', PERIODS[value.period] ?? 'No contratada'], ['Vigencia pagada hasta', date(value.paid_until)], ...(Object.hasOwn(value, 'renews_at') ? [['Renovación informada', date(value.renews_at)]] : [])];
        for (const [label, text] of fields) {
            const group = document.createElement('div'); group.append(element('dt', label, 'text-sm text-slate-500'), element('dd', text, 'mt-1 break-words font-semibold')); details.append(group);
        }
        const pending = find('subscription-pending'); pending.hidden = !value.pending_change;
        if (value.pending_change) pending.textContent = `Cambio pendiente: ${name(value.pending_change.plan_key)}, ${PERIODS[value.pending_change.period]}. ${value.pending_change.effective_at ? 'Fecha efectiva informada: '+date(value.pending_change.effective_at) : 'La fecha efectiva todavía no está confirmada.'} No se anticipan capacidades ni cargos.`;
    }
    function renderCatalog(plans, period) {
        const output = find('billing-catalog'); if (!output) return; output.replaceChildren();
        for (const plan of plans) {
            const card = element('article', '', 'min-w-0 rounded-2xl border border-slate-200 p-4');
            card.append(element('h3', plan.name, 'font-bold'), element('p', priceText(plan, period), 'mt-3 text-xl font-bold'), element('p', `${PERIODS[period]} · ${plan.limits.branches} sucursal(es) · ${plan.limits.cash_sessions ?? 'Sin límite comercial de'} cajas simultáneas`, 'mt-2 text-sm leading-6'));
            const list = element('ul', '', 'mt-3 space-y-1 text-xs text-slate-600');
            plan.features.forEach(feature => list.append(element('li', FEATURES[feature] ?? feature))); card.append(list); output.append(card);
        }
    }
    return { find, notice, renderState, renderCatalog };
}
