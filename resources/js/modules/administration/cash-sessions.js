import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { invalidateActiveAnomalies } from '@/data/anomalies';
import { formatDateTime, formatMoney } from '@/dashboard/formatters';
import { clearFieldErrors, mutate, responseMessage, setButtonBusy, showFieldErrors } from '@/modules/organization/shared';
import { notify } from '@/core/notifications';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { denominationTotal, formatMoneyMinor, parseMoneyMinor } from '@/core/cash-denominations';

async function get(path, query = {}, signal = null) {
    const options = { dispatchErrors: false, signal };
    try { return await api.get(path, query, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal?.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false }); return api.get(path, query, options);
    }
}

function validatePage(payload) {
    if (!Array.isArray(payload?.data) || !Number.isInteger(payload?.meta?.current_page) || !Number.isInteger(payload?.meta?.last_page)) throw new Error('CashSessionResource no devolvió una colección paginada válida.');
    return payload;
}

class AdminCashSessions {
    constructor(root, context) {
        this.root = root; this.context = context; this.filters = root.querySelector('[data-cash-filters]'); this.dialog = root.querySelector('[data-cash-dialog]'); this.closeForm = root.querySelector('[data-cash-close-form]');
        this.page = 1; this.meta = null; this.request = null; this.controller = null; this.session = null; this.opener = null; this.scrollOwner = Symbol('cash-close');
    }

    init() {
        this.filters.addEventListener('submit', (event) => { event.preventDefault(); this.page = 1; void this.load(); });
        this.root.querySelector('[data-cash-retry]').addEventListener('click', () => this.load());
        this.root.querySelector('[data-cash-previous]').addEventListener('click', () => this.changePage(-1));
        this.root.querySelector('[data-cash-next]').addEventListener('click', () => this.changePage(1));
        this.root.querySelector('[data-cash-body]').addEventListener('click', (event) => { const button = event.target.closest('[data-close-session]'); if (button) this.openClose(button.dataset.closeSession, button.dataset.registerName, button.dataset.openedBy); });
        ['[data-cash-dialog-close]', '[data-cash-dialog-cancel]'].forEach((selector) => this.root.querySelector(selector).addEventListener('click', () => this.dialog.close()));
        this.dialog.addEventListener('click', (event) => { if (event.target === this.dialog) this.dialog.close(); });
        this.root.querySelector('[data-denomination-add]').addEventListener('click', () => this.addDenomination());
        this.root.querySelector('[data-denomination-list]').addEventListener('input', (event) => {
            event.target.removeAttribute('aria-invalid');
            this.root.querySelector('[data-error-for="counted_denominations"]').hidden = true;
            this.updateTotal();
        });
        this.root.querySelector('[data-denomination-list]').addEventListener('click', (event) => { const remove = event.target.closest('[data-denomination-remove]'); if (remove) { remove.closest('[data-denomination-row]').remove(); this.updateTotal(); } });
        this.closeForm.addEventListener('submit', (event) => { event.preventDefault(); void this.closeSession(); });
        this.dialog.addEventListener('close', () => { unlockScroll(this.scrollOwner); this.opener?.focus(); this.opener = null; });
        window.addEventListener('pagehide', () => this.controller?.abort(), { once: true });
        void this.load();
    }

    changePage(delta) { const next = this.page + delta; if (this.request || next < 1 || next > Number(this.meta?.last_page ?? 1)) return; this.page = next; void this.load(); }

    load() {
        if (this.request) return this.request;
        const status = new FormData(this.filters).get('status'); const query = { page: this.page, per_page: 15, sort: 'opened_at', direction: 'desc' }; if (status) query.status = status;
        this.controller = new AbortController(); this.setState('loading'); this.setBusy(true);
        this.request = get('/cash-sessions', query, this.controller.signal).then(validatePage).then((payload) => { this.meta = payload.meta; this.renderRows(payload.data); this.setState(payload.data.length ? 'ready' : 'empty'); }).catch((error) => {
            if (!(error instanceof ApiError && error.code === 'request_aborted')) this.setState('error', responseMessage(error, error?.message || 'No fue posible cargar las sesiones.'));
        }).finally(() => { this.request = null; this.controller = null; this.setBusy(false); });
        return this.request;
    }

    renderRows(records) {
        const body = this.root.querySelector('[data-cash-body]'); body.replaceChildren();
        records.forEach((session) => {
            if (!Number.isInteger(session.id) || !Number.isInteger(session.opened_by) || typeof session.status !== 'string') throw new Error('CashSessionResource contiene campos inválidos.');
            if (session.status === 'abierta' && (session.expected_amount !== null || session.difference !== null)) throw new Error('El Backend expuso importes de arqueo durante una sesión abierta.');
            const row = document.createElement('tr'); const values = [session.cash_register?.name ?? `Caja #${session.cash_register_id}`, `Usuario #${session.opened_by}`, session.status_label ?? session.status, formatDateTime(session.opened_at, this.context.business.timezone), session.closed_at ? formatDateTime(session.closed_at, this.context.business.timezone) : '—'];
            values.forEach((value) => { const cell = document.createElement('td'); cell.className = 'px-5 py-4 text-gintly-text-secondary'; cell.textContent = value; row.appendChild(cell); });
            const action = document.createElement('td'); action.className = 'px-5 py-4 text-end';
            if (session.status === 'abierta' && this.context.capabilities.includes('caja.cerrar')) {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'min-h-11 rounded-xl border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-900'; button.dataset.closeSession = session.id; button.dataset.registerName = values[0]; button.dataset.openedBy = session.opened_by; button.textContent = 'Cierre administrativo'; action.appendChild(button);
            } else { action.textContent = '—'; }
            row.appendChild(action); body.appendChild(row);
        });
        this.root.querySelector('[data-cash-page]').textContent = `Página ${this.meta.current_page} de ${this.meta.last_page}`; this.root.querySelector('[data-cash-previous]').disabled = this.meta.current_page <= 1; this.root.querySelector('[data-cash-next]').disabled = this.meta.current_page >= this.meta.last_page;
    }

    openClose(id, registerName, openedBy) {
        this.session = { id: Number(id), registerName, openedBy: Number(openedBy) }; this.opener = document.activeElement instanceof HTMLElement ? document.activeElement : null; this.closeForm.reset(); clearFieldErrors(this.closeForm);
        this.root.querySelector('[data-cash-close-error]').hidden = true; this.root.querySelector('[data-denomination-list]').replaceChildren();
        this.closeForm.elements.closing_notes.required = this.session.openedBy !== this.context.identity.id;
        this.addDenomination(); this.addDenomination(); this.addDenomination();
        this.root.querySelector('[data-cash-close-context]').textContent = `${registerName} · sesión #${id} · abierta por usuario #${openedBy}`;
        lockScroll(this.scrollOwner); this.dialog.showModal();
    }

    addDenomination() {
        const row = document.createElement('div'); row.className = 'grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_44px] gap-3'; row.dataset.denominationRow = '';
        const value = document.createElement('input'); value.type = 'number'; value.min = '0.01'; value.step = '0.01'; value.inputMode = 'decimal'; value.placeholder = 'Valor'; value.className = 'min-h-11 rounded-xl border border-gintly-border px-3'; value.dataset.denominationValue = ''; value.setAttribute('aria-label', 'Valor de denominación');
        const qty = document.createElement('input'); qty.type = 'number'; qty.min = '0'; qty.step = '1'; qty.inputMode = 'numeric'; qty.placeholder = 'Cantidad'; qty.className = 'min-h-11 rounded-xl border border-gintly-border px-3'; qty.dataset.denominationQty = ''; qty.setAttribute('aria-label', 'Cantidad de denominación');
        const remove = document.createElement('button'); const icon = document.createElement('i');
        remove.type = 'button'; remove.className = 'grid size-11 place-items-center rounded-xl text-red-700 hover:bg-red-50'; remove.dataset.denominationRemove = ''; remove.setAttribute('aria-label', 'Quitar denominación');
        icon.className = 'fa-solid fa-trash'; icon.setAttribute('aria-hidden', 'true'); remove.appendChild(icon);
        row.append(value, qty, remove); this.root.querySelector('[data-denomination-list]').appendChild(row);
    }

    denominations() {
        return Array.from(this.root.querySelectorAll('[data-denomination-row]')).map((row) => ({ value: row.querySelector('[data-denomination-value]').value.trim(), qty: row.querySelector('[data-denomination-qty]').value.trim() })).filter((line) => line.value !== '' || line.qty !== '');
    }

    countedAmount(lines = this.denominations()) {
        return denominationTotal(lines);
    }

    validateDenominations() {
        let rows = Array.from(this.root.querySelectorAll('[data-denomination-row]'));
        if (rows.length === 0) {
            this.addDenomination();
            rows = Array.from(this.root.querySelectorAll('[data-denomination-row]'));
        }
        const populated = rows.filter((row) => {
            const value = row.querySelector('[data-denomination-value]').value.trim();
            const qty = row.querySelector('[data-denomination-qty]').value.trim();
            return value !== '' || qty !== '';
        });
        const error = this.closeForm.querySelector('[data-error-for="counted_denominations"]');
        let firstInvalid = null;

        error.hidden = true;
        rows.forEach((row) => row.querySelectorAll('input').forEach((input) => input.removeAttribute('aria-invalid')));

        if (populated.length === 0) {
            firstInvalid = rows[0]?.querySelector('[data-denomination-value]') ?? null;
            error.textContent = 'Registra al menos una denominación válida y una cantidad entera.';
        } else {
            populated.forEach((row) => {
                const value = row.querySelector('[data-denomination-value]');
                const qty = row.querySelector('[data-denomination-qty]');
                const cents = parseMoneyMinor(value.value);
                const quantity = /^\d+$/.test(qty.value.trim()) ? BigInt(qty.value.trim()) : null;

                if (cents === null || cents <= 0n) {
                    value.setAttribute('aria-invalid', 'true');
                    firstInvalid ??= value;
                }

                if (quantity === null || quantity > BigInt(Number.MAX_SAFE_INTEGER)) {
                    qty.setAttribute('aria-invalid', 'true');
                    firstInvalid ??= qty;
                }
            });
            error.textContent = 'Cada valor debe ser positivo, usar máximo dos decimales y cada cantidad debe ser un entero no negativo.';
        }

        if (firstInvalid) {
            error.hidden = false;
            firstInvalid.focus();
            return null;
        }

        const lines = populated.map((row) => {
            const value = row.querySelector('[data-denomination-value]').value.trim();
            const qty = row.querySelector('[data-denomination-qty]').value.trim();
            return { value: formatMoneyMinor(parseMoneyMinor(value)), qty: Number(qty) };
        });

        return { lines, amount: this.countedAmount(lines.map((line) => ({ ...line, qty: String(line.qty) }))) };
    }

    updateTotal() { const amount = this.countedAmount(); this.root.querySelector('[data-counted-amount]').textContent = amount === null ? 'Revisa el desglose' : formatMoney(amount); }

    async closeSession() {
        if (!this.session || this.closeForm.dataset.submitting === 'true') return;
        const errorBox = this.root.querySelector('[data-cash-close-error]');
        clearFieldErrors(this.closeForm); errorBox.hidden = true;
        const validation = this.validateDenominations();
        if (!validation) return;
        if (!this.closeForm.reportValidity()) return;
        const formData = new FormData(this.closeForm);
        const { lines, amount } = validation;
        const button = this.root.querySelector('[data-cash-close-submit]'); this.closeForm.dataset.submitting = 'true'; setButtonBusy(button, true, 'Cerrando…');
        try {
            const payload = await mutate('post', `/cash-sessions/${this.session.id}/close`, { counted_amount: amount, counted_denominations: lines, closing_notes: formData.get('closing_notes')?.toString().trim() || null });
            this.handlePersistedClose(payload?.data, false); notify({ type: 'success', message: 'La sesión de caja quedó cerrada.' });
        } catch (error) {
            const persisted = error instanceof ApiError && error.status === 422 && error.payload?.error === 'UNRECONCILED_CASH_CLOSING';
            if (persisted) this.handlePersistedClose(error.payload?.data?.data ?? error.payload?.data, true);
            else {
                if (error instanceof ApiError && error.status === 422) {
                    const first = showFieldErrors(this.closeForm, error.errors);
                    const denominationError = Object.entries(error.errors ?? {}).find(([field]) => field.startsWith('counted_denominations'));
                    if (!first && denominationError) {
                        const fieldError = this.closeForm.querySelector('[data-error-for="counted_denominations"]');
                        fieldError.textContent = Array.isArray(denominationError[1]) ? denominationError[1][0] : String(denominationError[1]);
                        fieldError.hidden = false;
                        this.closeForm.querySelector('[data-denomination-value]')?.focus();
                    }
                }
                errorBox.textContent = responseMessage(error, 'No fue posible cerrar la sesión.'); errorBox.hidden = false;
            }
        } finally { delete this.closeForm.dataset.submitting; setButtonBusy(button, false); }
    }

    handlePersistedClose(session, unreconciled) {
        if (!session || !['cerrada', 'descuadrada'].includes(session.status)) throw new Error('El Backend no devolvió una sesión cerrada válida.');
        this.dialog.close(); const result = this.root.querySelector('[data-cash-result]');
        result.className = `rounded-2xl border p-5 text-sm leading-6 ${unreconciled ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`;
        result.textContent = unreconciled ? `La sesión #${session.id} quedó persistida como descuadrada. Diferencia registrada: ${formatMoney(session.difference)}. Se generó evidencia/anomalía; no reintentes el cierre.` : `La sesión #${session.id} quedó cerrada correctamente.`;
        result.hidden = false; result.focus(); invalidateActiveAnomalies(); document.dispatchEvent(new CustomEvent('gintly:anomalies-invalidated')); void this.load();
    }

    setBusy(busy) { this.root.setAttribute('aria-busy', String(busy)); this.root.querySelector('[data-cash-filter-submit]').disabled = busy; }
    setState(state, message = '') { this.root.querySelector('[data-cash-loading]').hidden = state !== 'loading'; this.root.querySelector('[data-cash-error]').hidden = state !== 'error'; this.root.querySelector('[data-cash-empty]').hidden = state !== 'empty'; this.root.querySelector('[data-cash-content]').hidden = state !== 'ready'; if (state === 'error') this.root.querySelector('[data-cash-error-message]').textContent = message; }
}

export default async function init() {
    const root = document.querySelector('[data-admin-cash-sessions]'); if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; const context = await getSessionContext();
    if (context.role !== 'ROL-02' || !context.capabilities.includes('caja.gestionar')) throw new Error('La vista requiere administración de caja ROL-02.');
    new AdminCashSessions(root, context).init();
}
