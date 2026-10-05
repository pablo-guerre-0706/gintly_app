import { CashSessionDetail } from '@/modules/administration/cash-session-detail';
import { cashierContext, cashError, cashNotice, cashRead } from './cash-shared';
import { CashHistorySource, historyFilters } from './cash-history-source';
import { renderCashHistory } from './cash-history-view';

function detailButton(id) { const button = document.createElement('button'); button.type = 'button'; button.dataset.cashDetail = String(id); button.className = 'min-h-11 rounded-xl border border-slate-300 px-3 text-xs font-semibold'; button.textContent = 'Ver detalle'; return button; }

class CashHistory {
    constructor(root) {
        this.root = root; this.form = root.querySelector('[data-cash-filters]'); this.page = 1; this.last = 1; this.pending = false; this.context = null; this.detail = null;
        this.source = new CashHistorySource(cashRead, (sessions) => {
            if (sessions.some((session) => session.opened_by !== this.context.identity.id || session.cash_register?.branch_id !== this.context.branch.id)) {
                throw new TypeError('El historial recibido excede el alcance de tu sucursal.');
            }
        });
    }

    async init() {
        this.form.addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; this.source.reset(); void this.load(); });
        this.form.elements.view.addEventListener('change', () => { this.page = 1; this.source.reset(); void this.load(); });
        this.root.querySelector('[data-cash-retry]').addEventListener('click', () => { this.source.reset(); void this.load(); });
        this.root.querySelector('[data-cash-prev]').addEventListener('click', () => { if (this.pending || this.page <= 1) return; this.page -= 1; void this.load(); });
        this.root.querySelector('[data-cash-next]').addEventListener('click', () => { if (this.pending || this.page >= this.last) return; this.page += 1; void this.load(); });
        this.root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-cash-detail]');
            if (button && this.root.contains(button)) this.detail?.open(Number(button.dataset.cashDetail), button);
        });
        try {
            this.context = await cashierContext();
            this.detail = new CashSessionDetail(this.root, this.context.business.timezone);
            void this.loadRegisters();
            await this.load();
        } catch (error) { this.root.querySelector('[data-cash-loading]').hidden = true; cashNotice(this.root, cashError(error), true); }
    }

    async loadRegisters() {
        try {
            const select = this.form.elements.namedItem('cash_register_id');
            let page = 1; let last = 1;
            do {
                const response = await cashRead('/cash-registers', { page, per_page: 100 });
                if (!Array.isArray(response?.data) || !Number.isInteger(response?.meta?.last_page)) throw new TypeError('Cajas sin paginación válida.');
                select.append(...response.data.filter((item) => item.branch_id === this.context.branch.id).map((item) => new Option(item.name, String(item.id))));
                last = response.meta.last_page; page += 1;
            } while (page <= last);
        } catch { this.form.elements.namedItem('cash_register_id').disabled = true; }
    }

    async load() {
        if (this.pending) return;
        this.pending = true; this.root.setAttribute('aria-busy', 'true');
        this.root.querySelector('[data-cash-notice]').hidden = true;
        this.root.querySelector('[data-cash-loading]').hidden = false;
        this.root.querySelector('[data-cash-results]').hidden = true;
        this.root.querySelector('[data-cash-empty]').hidden = true;
        this.root.querySelector('[data-cash-retry]').hidden = true;
        try {
            const filters = historyFilters(new FormData(this.form));
            this.form.querySelectorAll('input, select, button').forEach((control) => { control.disabled = true; });
            // La API fuerza opened_by al usuario ROL-03 autenticado, incluso si se manipula la URL.
            const response = await this.source.load(filters, this.page);
            this.page = response.meta.current_page;
            this.last = response.meta.last_page;
            this.render(response.data, response.meta, filters.view);
            this.root.querySelector(response.data.length ? '[data-cash-results]' : '[data-cash-empty]').hidden = false;
            const requested = Number(new URLSearchParams(window.location.search).get('session'));
            const matching = response.data.find((session) => session.id === requested);
            if (matching && !this.root.dataset.detailOpened) { this.root.dataset.detailOpened = 'true'; this.detail.open(requested, this.root.querySelector(`[data-cash-detail="${requested}"]`)); }
        } catch (error) { cashNotice(this.root, cashError(error), true); this.root.querySelector('[data-cash-retry]').hidden = false; }
        finally { this.pending = false; this.form.querySelectorAll('input, select, button').forEach((control) => { control.disabled = false; }); this.root.querySelector('[data-cash-loading]').hidden = true; this.root.setAttribute('aria-busy', 'false'); }
    }

    render(sessions, meta, view) {
        renderCashHistory(this.root.querySelector('[data-cash-cards]'), sessions, {
            view, timezone: this.context.business.timezone, cashier: () => this.context.identity.name,
            actions: (session) => [detailButton(session.id)],
        });
        this.root.querySelector('[data-cash-page]').textContent = `Página ${meta.current_page} de ${meta.last_page} · ${meta.total} sesiones`;
        this.root.querySelector('[data-cash-prev]').disabled = this.page <= 1;
        this.root.querySelector('[data-cash-next]').disabled = this.page >= this.last;
    }
}

export default function init() {
    const root = document.querySelector('[data-cash-history]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; void new CashHistory(root).init();
}
