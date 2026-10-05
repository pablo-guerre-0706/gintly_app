import { formatDateTime } from '@/dashboard/formatters';
import { cashLabel } from './cash-shared';

function text(tag, value, className = '') {
    const node = document.createElement(tag); node.textContent = value; node.className = className; return node;
}

function currencyPair(list, label, nio, usd) {
    for (const [currency, amount] of [['NIO', nio], ['USD', usd]]) {
        const line = document.createElement('div'); line.className = 'flex min-w-0 flex-wrap justify-between gap-x-4 gap-y-1';
        line.append(text('dt', `${label} ${currency}`, 'text-slate-600'), text('dd', amount === null ? 'No registrado' : cashLabel(amount, currency), 'break-words font-semibold tabular-nums'));
        list.append(line);
    }
}

export function renderCashHistory(container, sessions, { view, timezone, cashier, actions }) {
    container.replaceChildren();
    for (const session of sessions) {
        if (session.status === 'abierta' && (session.expected_amount !== null || session.difference !== null
            || session.expected_amount_usd !== null || session.difference_usd !== null)) {
            throw new TypeError('La sesión abierta reveló importes de arqueo ciego.');
        }
        const card = document.createElement('article');
        card.className = 'min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6';
        card.dataset.cashSession = String(session.id);
        const header = document.createElement('header'); header.className = 'flex flex-wrap items-start justify-between gap-3';
        const identity = document.createElement('div'); identity.className = 'min-w-0 space-y-1';
        identity.append(text('h2', `Sesión #${session.id}`, 'text-base font-bold text-gintly-sidebar'),
            text('p', session.cash_register?.name ?? `Caja #${session.cash_register_id}`, 'break-words text-sm font-semibold'),
            text('p', `Cajero: ${cashier(session)} · Sucursal #${session.cash_register?.branch_id}`, 'break-words text-sm text-slate-600'));
        header.append(identity, text('span', session.status_label ?? session.status, 'rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold'));
        card.append(header);
        const sections = document.createElement('div'); sections.className = 'mt-4 grid min-w-0 gap-4';
        const openingVisible = ['all', 'openings', 'open'].includes(view);
        const closingVisible = ['all', 'closings', 'unbalanced'].includes(view);
        if (openingVisible && closingVisible) sections.classList.add('md:grid-cols-2');
        if (openingVisible) {
            const section = document.createElement('section'); section.className = 'min-w-0 rounded-xl bg-slate-50 p-4';
            section.append(text('h3', 'Apertura', 'font-semibold text-gintly-brand'), text('p', formatDateTime(session.opened_at, timezone), 'mt-1 text-sm text-slate-600'));
            const fields = document.createElement('dl'); fields.className = 'mt-3 space-y-2 text-sm';
            currencyPair(fields, 'Fondo inicial', session.opening_amount, session.opening_amount_usd);
            section.append(fields); sections.append(section);
        }
        if (closingVisible) {
            const section = document.createElement('section'); section.className = 'min-w-0 rounded-xl border border-slate-200 p-4';
            section.append(text('h3', 'Cierre', 'font-semibold text-gintly-brand'));
            if (!session.closed_at) section.append(text('p', 'Sin cierre. La sesión permanece abierta; el arqueo es ciego.', 'mt-2 text-sm text-slate-600'));
            else {
                section.append(text('p', formatDateTime(session.closed_at, timezone), 'mt-1 text-sm text-slate-600'));
                const fields = document.createElement('dl'); fields.className = 'mt-3 space-y-2 text-sm';
                currencyPair(fields, 'Contado', session.counted_amount, session.counted_amount_usd);
                currencyPair(fields, 'Diferencia', session.difference, session.difference_usd);
                section.append(fields);
            }
            sections.append(section);
        }
        card.append(sections);
        const controls = document.createElement('div'); controls.className = 'mt-4 flex flex-wrap gap-3';
        controls.append(...actions(session)); card.append(controls); container.append(card);
    }
}
