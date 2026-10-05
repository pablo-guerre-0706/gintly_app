import { ApiError } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { invalidateActiveAnomalies } from '@/data/anomalies';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { CashDenominationGrid } from '@/modules/operations/cash-denomination-grid';
import { cashError, cashFieldErrors, cashLabel, cashPost, cashRead } from '@/modules/operations/cash-shared';
import { CashSessionDetail } from './cash-session-detail';
import { CashHistorySource, historyFilters } from '@/modules/operations/cash-history-source';
import { renderCashHistory } from '@/modules/operations/cash-history-view';

function action(label, attribute, session) {
    const button = document.createElement('button'); button.type = 'button'; button.className = 'min-h-11 rounded-xl border border-slate-300 px-3 text-xs font-semibold';
    button.dataset[attribute] = String(session.id); button.dataset.registerName = session.cash_register?.name ?? `Caja #${session.cash_register_id}`;
    button.dataset.openedBy = String(session.opened_by); button.textContent = label; return button;
}

class AdminCashSessions {
    constructor(root, context) {
        this.root = root; this.context = context; this.filters = root.querySelector('[data-cash-filters]'); this.dialog = root.querySelector('[data-cash-dialog]'); this.form = root.querySelector('[data-cash-close-form]');
        this.page = 1; this.last = 1; this.pending = false; this.writing = false; this.target = null; this.opener = null; this.scrollOwner = Symbol('cash-admin'); this.users = new Map();
        this.detail = new CashSessionDetail(root, context.business.timezone); this.grid = new CashDenominationGrid(this.form);
        this.source = new CashHistorySource(cashRead);
    }

    init() {
        this.filters.addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; this.source.reset(); void this.load(); });
        this.filters.elements.view.addEventListener('change', () => { this.page = 1; this.source.reset(); void this.load(); });
        this.root.querySelector('[data-cash-retry]').addEventListener('click', () => { this.source.reset(); void this.load(); });
        this.root.querySelector('[data-cash-previous]').addEventListener('click', () => { if (this.pending || this.page <= 1) return; this.page -= 1; void this.load(); });
        this.root.querySelector('[data-cash-next]').addEventListener('click', () => { if (this.pending || this.page >= this.last) return; this.page += 1; void this.load(); });
        this.root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-cash-detail],[data-count-session],[data-close-session]');
            if (!button) return;
            if (button.dataset.cashDetail) this.detail.open(Number(button.dataset.cashDetail), button);
            else this.openDialog(button.dataset.countSession ? 'count' : 'close', button);
        });
        for (const key of ['[data-cash-dialog-close]', '[data-cash-dialog-cancel]']) this.root.querySelector(key).addEventListener('click', () => this.dialog.close());
        this.dialog.addEventListener('click', (event) => { if (event.target === this.dialog) this.dialog.close(); });
        this.dialog.addEventListener('close', () => { unlockScroll(this.scrollOwner); this.opener?.focus(); this.opener = null; });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        void this.loadOptions(); void this.load();
    }

    async allPages(path) {
        const results = []; let page = 1; let last = 1;
        do { const response = await cashRead(path, { page, per_page: 100 }); if (!Array.isArray(response?.data) || !Number.isInteger(response?.meta?.last_page)) throw new TypeError('Paginación inválida.'); results.push(...response.data); last = response.meta.last_page; page += 1; } while (page <= last);
        return results;
    }

    async loadOptions() {
        const note = this.root.querySelector('[data-cash-filter-note]');
        try {
            const [registers, userRecords] = await Promise.all([this.allPages('/cash-registers'), this.allPages('/users')]);
            const users = userRecords.filter((user) => user.role !== 'ROL-SYS');
            this.users = new Map(users.map((user) => [user.id, user.name]));
            this.filters.elements.cash_register_id.append(...registers.map((register) => new Option(`${register.name} · Sucursal #${register.branch_id}`, String(register.id))));
            this.filters.elements.opened_by.append(...users.map((user) => new Option(user.name, String(user.id))));
            note.textContent = 'El período filtra la fecha de apertura. Cierres y descuadres se ordenan por fecha de cierre. La sucursal se identifica en cada caja; no existe un filtro directo por sucursal.';
            if (this.records) this.render(this.records, this.meta);
        } catch (error) { note.textContent = cashError(error); this.filters.elements.cash_register_id.disabled = true; this.filters.elements.opened_by.disabled = true; }
    }

    async load() {
        if (this.pending) return;
        this.pending = true; this.state('loading'); this.root.setAttribute('aria-busy', 'true');
        try {
            const filters = historyFilters(new FormData(this.filters));
            this.filters.querySelectorAll('input, select, button').forEach((control) => { control.disabled = true; });
            const response = await this.source.load(filters, this.page);
            this.view = filters.view; this.page = response.meta.current_page;
            this.records = response.data; this.meta = response.meta; this.last = response.meta.last_page;
            this.render(response.data, response.meta); this.state(response.data.length ? 'ready' : 'empty');
        } catch (error) { this.state('error', cashError(error)); }
        finally { this.pending = false; this.filters.querySelectorAll('input, select, button').forEach((control) => { control.disabled = false; }); this.root.setAttribute('aria-busy', 'false'); }
    }

    render(records, meta) {
        renderCashHistory(this.root.querySelector('[data-cash-cards]'), records, {
            view: this.view, timezone: this.context.business.timezone,
            cashier: (session) => this.users.get(session.opened_by) ?? `Usuario #${session.opened_by}`,
            actions: (session) => {
                const buttons = [action('Detalle', 'cashDetail', session)];
                if (session.status === 'abierta' && this.context.capabilities.includes('caja.movimiento.crear')) buttons.push(action('Arqueo', 'countSession', session));
                if (session.status === 'abierta' && this.context.capabilities.includes('caja.cerrar')) buttons.push(action('Cierre administrativo', 'closeSession', session));
                return buttons;
            },
        });
        this.root.querySelector('[data-cash-page]').textContent = `Página ${meta.current_page} de ${meta.last_page} · ${meta.total} sesiones`;
        this.root.querySelector('[data-cash-previous]').disabled = this.page <= 1; this.root.querySelector('[data-cash-next]').disabled = this.page >= this.last;
    }

    openDialog(mode, button) {
        if (this.dialog.open) return;
        this.target = { id: Number(button.dataset[mode === 'count' ? 'countSession' : 'closeSession']), openedBy: Number(button.dataset.openedBy), mode };
        this.opener = button; this.form.reset(); this.grid.reset(); cashFieldErrors(this.form); this.root.querySelector('[data-cash-close-error]').hidden = true;
        const counting = mode === 'count';
        this.form.elements.closing_notes.required = !counting && this.target.openedBy !== this.context.identity.id;
        this.form.elements.confirmation.required = !counting;
        this.root.querySelector('[data-cash-close-notes-block]').hidden = counting; this.root.querySelector('[data-cash-close-confirm-block]').hidden = counting;
        this.root.querySelector('[data-cash-dialog-eyebrow]').textContent = counting ? 'Supervisión de efectivo' : 'Excepción administrativa';
        this.root.querySelector('[data-cash-dialog-title]').textContent = counting ? 'Arqueo ciego independiente' : 'Cierre administrativo';
        this.root.querySelector('[data-cash-dialog-help]').textContent = counting ? 'El esperado y la diferencia se revelan después de guardar. La sesión permanece abierta.' : 'El cierre registra evidencia irreversible NIO/USD. El esperado y la diferencia no se revelan antes de persistir.';
        this.root.querySelector('[data-cash-close-submit]').textContent = counting ? 'Registrar arqueo' : 'Confirmar cierre administrativo';
        this.root.querySelector('[data-cash-close-context]').textContent = `${button.dataset.registerName} · sesión #${this.target.id} · abierta por usuario #${this.target.openedBy}`;
        lockScroll(this.scrollOwner); this.dialog.showModal(); this.form.querySelector('[data-cash-grid="NIO"] input[type="number"]')?.focus();
    }

    async submit() {
        if (this.writing || !this.target) return;
        cashFieldErrors(this.form); const errorBox = this.root.querySelector('[data-cash-close-error]'); errorBox.hidden = true;
        let payload; try { payload = this.grid.payload(); } catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; return; }
        if (!this.form.reportValidity()) return;
        const counting = this.target.mode === 'count'; if (!counting) payload.closing_notes = this.form.elements.closing_notes.value.trim() || null;
        this.writing = true; const button = this.root.querySelector('[data-cash-close-submit]'); button.disabled = true; this.form.setAttribute('aria-busy', 'true');
        try {
            const response = await cashPost(`/cash-sessions/${this.target.id}/${counting ? 'counts' : 'close'}`, payload, counting ? 201 : 200);
            this.persisted(response?.data, counting, false);
        } catch (error) {
            const persisted = !counting && error instanceof ApiError && error.status === 422 && error.code === 'UNRECONCILED_CASH_CLOSING';
            if (persisted) this.persisted(error.payload?.data?.data ?? error.payload?.data, false, true);
            else { if (error instanceof ApiError && error.status === 422) cashFieldErrors(this.form, error.errors); errorBox.textContent = cashError(error); errorBox.hidden = false; }
        } finally { this.writing = false; button.disabled = false; this.form.setAttribute('aria-busy', 'false'); }
    }

    persisted(resource, counting, unreconciled) {
        if (!Number.isInteger(resource?.id)) throw new TypeError('La respuesta no confirmó el resultado. Consulta el historial antes de repetirlo.');
        this.dialog.close(); const result = this.root.querySelector('[data-cash-result]');
        result.textContent = `${counting ? `Arqueo #${resource.id} registrado; la sesión permanece abierta` : `Sesión #${resource.id} ${unreconciled ? 'descuadrada y persistida; no reintentes' : 'cerrada'}`}. NIO: esperado ${cashLabel(resource.expected_amount)}, contado ${cashLabel(resource.counted_amount)}, diferencia ${cashLabel(resource.difference)}. USD: esperado ${cashLabel(resource.expected_amount_usd, 'USD')}, contado ${cashLabel(resource.counted_amount_usd, 'USD')}, diferencia ${cashLabel(resource.difference_usd, 'USD')}.${resource.consolidated_nio ? ` Consolidado informativo ${cashLabel(resource.consolidated_nio.counted_amount)} a tasa snapshot ${resource.consolidated_nio.reference_rate}.` : ''}`;
        result.hidden = false; result.focus();
        this.source.reset();
        if (!counting) { invalidateActiveAnomalies(); document.dispatchEvent(new CustomEvent('gintly:anomalies-invalidated')); }
        void this.load();
    }

    state(value, message = '') { for (const name of ['loading', 'error', 'empty', 'content']) this.root.querySelector(`[data-cash-${name}]`).hidden = name !== (value === 'ready' ? 'content' : value); if (value === 'error') this.root.querySelector('[data-cash-error-message]').textContent = message; }
}

export default async function init() {
    const root = document.querySelector('[data-admin-cash-sessions]'); if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; const context = await getSessionContext();
    if (!['ROL-01', 'ROL-02'].includes(context.role) || !context.capabilities.includes('caja.gestionar')) throw new TypeError('La supervisión de caja no está autorizada.');
    new AdminCashSessions(root, context).init();
}
