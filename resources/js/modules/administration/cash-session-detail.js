import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { formatDateTime } from '@/dashboard/formatters';
import { responseMessage } from '@/modules/organization/shared';
import { cashLabel, cashRead } from '@/modules/operations/cash-shared';

function field(label, value) {
    const wrapper = document.createElement('div');
    wrapper.className = 'rounded-xl bg-slate-50 p-3';
    const term = document.createElement('dt');
    term.className = 'text-gintly-text-secondary';
    term.textContent = label;
    const description = document.createElement('dd');
    description.className = 'mt-1 font-semibold';
    description.textContent = value ?? '—';
    wrapper.append(term, description);
    return wrapper;
}

function line(text) {
    const item = document.createElement('li');
    item.className = 'px-4 py-3 text-sm';
    item.textContent = text;
    return item;
}

export class CashSessionDetail {
    constructor(root, timezone) {
        this.dialog = root.querySelector('[data-cash-detail-dialog]');
        this.timezone = timezone;
        this.scrollOwner = Symbol('cash-detail');
        this.opener = null;
        this.sessionId = null;
        this.page = 0;
        this.lastPage = 1;
        this.pending = false;
        this.controller = null;
        this.dialog.querySelector('[data-cash-detail-close]').addEventListener('click', () => this.dialog.close());
        this.dialog.querySelector('[data-cash-detail-more]').addEventListener('click', () => { void this.loadMovements(); });
        this.dialog.querySelector('[data-cash-detail-counts-retry]').addEventListener('click', () => { void this.loadCounts(); });
        this.dialog.addEventListener('click', (event) => { if (event.target === this.dialog) this.dialog.close(); });
        this.dialog.addEventListener('close', () => {
            this.controller?.abort();
            this.controller = null;
            unlockScroll(this.scrollOwner);
            this.opener?.focus();
            this.opener = null;
        });
    }

    open(id, opener) {
        if (this.dialog.open) return;
        this.sessionId = id;
        this.opener = opener;
        this.page = 0;
        this.lastPage = 1;
        this.dialog.querySelector('[data-cash-detail-content]').hidden = true;
        this.dialog.querySelector('[data-cash-detail-state]').textContent = 'Cargando detalle…';
        lockScroll(this.scrollOwner);
        this.dialog.showModal();
        this.dialog.querySelector('[data-cash-detail-close]').focus();
        void this.load();
    }

    async load() {
        this.controller = new AbortController();
        const signal = this.controller.signal;
        const state = this.dialog.querySelector('[data-cash-detail-state]');
        try {
            const payload = await cashRead(`/cash-sessions/${this.sessionId}`, {}, signal);
            if (!payload?.data || payload.data.id !== this.sessionId) throw new TypeError('El detalle de sesión no coincide con la solicitud.');
            if (signal.aborted) return;
            this.render(payload.data);
            state.hidden = true;
            this.dialog.querySelector('[data-cash-detail-content]').hidden = false;
            await Promise.all([this.loadMovements(), this.loadCounts()]);
        } catch (error) {
            if (!signal.aborted) state.textContent = responseMessage(error, 'No fue posible consultar esta sesión.');
        }
    }

    render(session) {
        const open = session.status === 'abierta';
        if (open && (session.expected_amount !== null || session.difference !== null || session.expected_amount_usd !== null || session.difference_usd !== null)) throw new TypeError('Se recibieron importes que rompen el arqueo ciego.');
        const timezone = this.timezone;
        const fields = [
            field('Sesión', `#${session.id}`),
            field('Caja', session.cash_register?.name ?? `Caja #${session.cash_register_id}`),
            field('Sucursal', session.cash_register?.branch_id ? `Sucursal #${session.cash_register.branch_id}` : null),
            field('Estado', session.status_label ?? session.status),
            field('Abierta por', `Usuario #${session.opened_by}`),
            field('Apertura', formatDateTime(session.opened_at, timezone)),
            field('Fondo inicial NIO', cashLabel(session.opening_amount)),
            field('Fondo inicial USD', cashLabel(session.opening_amount_usd, 'USD')),
        ];
        if (!open) fields.push(
            field('Cerrada por', session.closed_by ? `Usuario #${session.closed_by}` : null),
            field('Cierre', session.closed_at ? formatDateTime(session.closed_at, timezone) : null),
            field('Contado NIO', session.counted_amount === null ? null : cashLabel(session.counted_amount)),
            field('Esperado NIO', session.expected_amount === null ? null : cashLabel(session.expected_amount)),
            field('Diferencia NIO', session.difference === null ? null : cashLabel(session.difference)),
            field('Contado USD', session.counted_amount_usd === null ? null : cashLabel(session.counted_amount_usd, 'USD')),
            field('Esperado USD', session.expected_amount_usd === null ? null : cashLabel(session.expected_amount_usd, 'USD')),
            field('Diferencia USD', session.difference_usd === null ? null : cashLabel(session.difference_usd, 'USD')),
            field('Tasa snapshot de cierre', session.session_exchange_rate),
            field('Consolidado NIO informativo', session.consolidated_nio ? cashLabel(session.consolidated_nio.counted_amount) : null),
            field('Notas de cierre', session.closing_notes),
        );
        this.dialog.querySelector('[data-cash-detail-fields]').replaceChildren(...fields);
        const breakdown = this.dialog.querySelector('[data-cash-detail-denominations]');
        breakdown.replaceChildren(...(open ? [line('Disponible solamente después del cierre.')] : [
            ...(Array.isArray(session.counted_denominations) ? session.counted_denominations.map(({ value, qty }) => line(`NIO · ${value} × ${qty}`)) : []),
            ...(Array.isArray(session.counted_denominations_usd) ? session.counted_denominations_usd.map(({ value, qty }) => line(`USD · ${value} × ${qty}`)) : []),
        ]));
    }

    async loadMovements() {
        if (this.pending || this.page >= this.lastPage || !this.dialog.open) return;
        this.pending = true;
        const more = this.dialog.querySelector('[data-cash-detail-more]');
        const list = this.dialog.querySelector('[data-cash-detail-movements]');
        more.disabled = true;
        const signal = this.controller?.signal;
        try {
            const payload = await cashRead(`/cash-sessions/${this.sessionId}/movements`, { page: this.page + 1, per_page: 20 }, signal);
            if (!Array.isArray(payload?.data) || !Number.isInteger(payload?.meta?.last_page)) throw new TypeError('Los movimientos no devolvieron paginación válida.');
            if (signal?.aborted) return;
            if (this.page === 0) list.replaceChildren();
            list.append(...payload.data.map((movement) => line(`${formatDateTime(movement.created_at, this.timezone)} · ${movement.category_label} · ${movement.payment_method_label} · ${movement.type === 'egreso' ? '−' : '+'}${cashLabel(movement.amount, movement.currency)}${movement.exchange_rate ? ` · tasa snapshot ${movement.exchange_rate}` : ''}`)));
            if (this.page === 0 && payload.data.length === 0) list.append(line('Esta sesión no tiene movimientos.'));
            this.page = payload.meta.current_page;
            this.lastPage = payload.meta.last_page;
            more.hidden = this.page >= this.lastPage;
            more.textContent = 'Cargar más movimientos';
        } catch (error) {
            if (!signal?.aborted) {
                more.hidden = false;
                more.textContent = responseMessage(error, 'No fue posible cargar los movimientos. Reintentar');
            }
        } finally { this.pending = false; more.disabled = false; }
    }

    async loadCounts() {
        if (!this.dialog.open) return;
        const state = this.dialog.querySelector('[data-cash-detail-counts-state]');
        const list = this.dialog.querySelector('[data-cash-detail-counts]');
        const retry = this.dialog.querySelector('[data-cash-detail-counts-retry]');
        state.hidden = false; state.textContent = 'Cargando arqueos…'; list.hidden = true; retry.hidden = true;
        const signal = this.controller?.signal;
        try {
            const payload = await cashRead(`/cash-sessions/${this.sessionId}/counts`, {}, signal);
            if (!Array.isArray(payload?.data) || payload.data.some((count) => count.cash_session_id !== this.sessionId || !Number.isInteger(count.id))) {
                throw new TypeError('El servidor no devolvió el historial de arqueos de esta sesión.');
            }
            if (signal?.aborted) return;
            if (!payload.data.length) { state.textContent = 'Esta sesión no tiene arqueos independientes.'; return; }
            list.replaceChildren(...payload.data.map((count) => line(
                `#${count.id} · ${formatDateTime(count.counted_at, this.timezone)} · usuario #${count.user_id} · NIO contado ${cashLabel(count.counted_amount)}, esperado ${cashLabel(count.expected_amount)}, diferencia ${cashLabel(count.difference)} · USD contado ${cashLabel(count.counted_amount_usd, 'USD')}, esperado ${cashLabel(count.expected_amount_usd, 'USD')}, diferencia ${cashLabel(count.difference_usd, 'USD')} · NIO: ${Array.isArray(count.counted_denominations) ? count.counted_denominations.map(({ value, qty }) => `${value} × ${qty}`).join(', ') : 'sin desglose'} · USD: ${Array.isArray(count.counted_denominations_usd) ? count.counted_denominations_usd.map(({ value, qty }) => `${value} × ${qty}`).join(', ') : 'sin desglose'}`,
            )));
            state.hidden = true; list.hidden = false;
        } catch (error) {
            if (!signal?.aborted) { state.textContent = responseMessage(error, 'No fue posible consultar los arqueos.'); retry.hidden = false; }
        }
    }
}
