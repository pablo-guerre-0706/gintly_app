import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { formatDate, formatMoney } from '@/dashboard/formatters';
import { compare, money } from '@/core/money';
import { getSessionContext } from '@/core/session-context';

const MONEY_PATTERN = /^\d+(?:\.\d{1,2})?$/;

function currentCashSession(payload) {
    if (!payload || typeof payload !== 'object' || !('data' in payload)
        || (payload.data !== null && !Number.isInteger(payload.data?.id))) {
        throw new TypeError('El servidor no devolvió un estado de caja válido.');
    }
    return payload.data;
}

async function mutate(path, data) {
    const options = { dispatchErrors: false };
    try { return await api.post(path, data, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.post(path, data, options);
    }
}

function message(error) {
    if (!(error instanceof ApiError)) return error instanceof TypeError ? error.message : 'No fue posible completar la operación.';
    if (error.status === 403) return 'No tienes autorización para registrar este abono.';
    if ([409, 422].includes(error.status)) return error.message;
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo completar la operación.';
    return error.message;
}

class ReceivablesPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-receivable-payment-form]');
        this.records = new Map();
        this.context = null;
        this.cashSession = null;
        this.timer = null;
        this.controller = null;
        this.pending = null;
        this.activeSearch = null;
        this.submitting = false;
    }

    async init() {
        this.context = await getSessionContext();
        const canPay = this.context.capabilities.includes('cuentas_por_cobrar.abonar');
        if (canPay) {
            try {
                const current = await api.get('/cash-sessions/current', {}, { dispatchErrors: false });
                this.cashSession = currentCashSession(current);
            } catch (error) {
                this.root.querySelector('[data-receivables-live]').textContent = `No fue posible verificar la sesión de caja: ${message(error)}`;
            }
        }
        this.root.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-receivable-pay]');
            if (trigger) this.select(trigger.dataset.receivablePay);
            if (event.target.closest('[data-receivable-cancel]')) this.closeForm();
            if (event.target.closest('[data-receivables-retry]')) void this.load();
        });
        this.root.querySelector('[data-receivables-search]').addEventListener('input', () => {
            window.clearTimeout(this.timer);
            this.timer = window.setTimeout(() => this.load(), 300);
        });
        this.root.querySelector('[name="payment_method"]').addEventListener('change', () => this.syncPaymentMethod());
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        await this.load();
    }

    setState(text, error = false) {
        const state = this.root.querySelector('[data-receivables-state]');
        state.hidden = false;
        state.className = `p-5 text-sm ${error ? 'text-red-800' : 'text-gintly-text-secondary'}`;
        state.replaceChildren(document.createTextNode(text));
        if (error) {
            const retry = document.createElement('button');
            retry.type = 'button'; retry.dataset.receivablesRetry = '';
            retry.className = 'mt-4 block min-h-11 rounded-xl border border-red-300 px-4 font-semibold'; retry.textContent = 'Reintentar';
            state.appendChild(retry);
        }
        this.root.querySelector('[data-receivables-list]').hidden = true;
    }

    load() {
        const search = this.root.querySelector('[data-receivables-search]').value.trim();
        if (this.pending && this.activeSearch === search) return this.pending;
        this.controller?.abort();
        const controller = new AbortController();
        this.controller = controller;
        this.activeSearch = search;
        this.root.setAttribute('aria-busy', 'true');
        this.setState('Cargando cuentas…');
        const pending = api.get('/accounts-receivable/collectible', { search: search || undefined, per_page: 50 }, { dispatchErrors: false, signal: controller.signal })
        .then((response) => {
            if (controller.signal.aborted) return;
            if (!Array.isArray(response?.data)) throw new TypeError('El servidor no devolvió una lista cobrable válida.');
            const records = response.data;
            this.records = new Map(records.map((record) => [String(record.id), record]));
            if (!records.length) { this.setState('No hay cuentas cobrables que coincidan con la consulta.'); return; }
            const list = this.root.querySelector('[data-receivables-list]');
            list.replaceChildren(...records.map((record) => this.row(record)));
            list.hidden = false; this.root.querySelector('[data-receivables-state]').hidden = true;
            this.root.querySelector('[data-receivables-summary]').textContent = `${response.meta?.total ?? records.length} cuentas cobrables`;
        })
        .catch((error) => { if (!controller.signal.aborted) this.setState(message(error), true); })
        .finally(() => {
            if (this.controller !== controller) return;
            this.pending = null;
            this.root.setAttribute('aria-busy', 'false');
        });
        this.pending = pending;
        return pending;
    }

    row(record) {
        const item = document.createElement('li');
        const body = document.createElement('div');
        const title = document.createElement('p');
        const meta = document.createElement('p');
        const amount = document.createElement('div');
        item.className = 'flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between';
        body.className = 'min-w-0'; title.className = 'font-semibold'; meta.className = 'mt-1 text-sm text-gintly-text-secondary';
        title.textContent = `${record.customer_name ?? 'Cliente'} · ${record.invoice_folio ?? `Factura #${record.invoice_id}`}`;
        meta.textContent = `Vence: ${formatDate(record.due_date)} · Estado: ${record.status}`;
        body.append(title, meta);
        const balance = document.createElement('strong'); balance.className = 'block text-end text-lg'; balance.textContent = formatMoney(record.balance);
        amount.appendChild(balance);
        if (this.context.capabilities.includes('cuentas_por_cobrar.abonar')) {
            const button = document.createElement('button');
            button.type = 'button'; button.dataset.receivablePay = String(record.id);
            button.className = 'mt-2 min-h-11 rounded-xl border border-gintly-brand px-4 text-sm font-semibold text-gintly-brand'; button.textContent = 'Registrar abono';
            amount.appendChild(button);
        }
        item.append(body, amount); return item;
    }

    select(id) {
        const record = this.records.get(String(id)); if (!record) return;
        this.form.elements.receivable_id.value = String(record.id);
        this.form.elements.amount.value = '';
        this.form.querySelector('[data-receivable-selection]').textContent = `${record.customer_name ?? 'Cliente'} · saldo ${formatMoney(record.balance)}`;
        this.form.hidden = false; this.form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        this.form.elements.amount.focus(); this.syncPaymentMethod();
    }

    closeForm() { this.form.hidden = true; this.form.reset(); this.clearErrors(); }

    syncPaymentMethod() {
        const cash = this.form.elements.payment_method.value === 'efectivo';
        this.form.querySelector('[data-cash-payment-warning]').hidden = !cash || Boolean(this.cashSession);
    }

    clearErrors() { this.form.querySelectorAll('[data-payment-error]').forEach((element) => { element.textContent = ''; }); }

    showErrors(errors) {
        let first = null;
        Object.entries(errors ?? {}).forEach(([field, values]) => {
            const key = field.split('.')[0]; const output = this.form.querySelector(`[data-payment-error="${key}"]`); const control = this.form.elements.namedItem(key);
            if (output) output.textContent = Array.isArray(values) ? values[0] : String(values);
            if (!first && control instanceof HTMLElement) first = control;
        }); first?.focus();
    }

    async submit() {
        if (this.submitting) return;
        this.clearErrors();
        const id = this.form.elements.receivable_id.value;
        const record = this.records.get(id);
        const amountValue = this.form.elements.amount.value.trim();
        if (!record) {
            this.root.querySelector('[data-receivables-live]').textContent = 'La cuenta seleccionada ya no está disponible. Actualiza la lista e inténtalo nuevamente.';
            return;
        }
        if (!MONEY_PATTERN.test(amountValue) || compare(amountValue, '0') <= 0 || compare(amountValue, record.balance) > 0) {
            this.showErrors({ amount: ['El monto debe ser mayor que cero y no superar el saldo.'] }); return;
        }
        const method = this.form.elements.payment_method.value;
        const button = this.form.querySelector('[data-receivable-payment-submit]');
        this.submitting = true; button.disabled = true; this.form.setAttribute('aria-busy', 'true');
        try {
            if (method === 'efectivo') {
                const current = await api.get('/cash-sessions/current', {}, { dispatchErrors: false });
                this.cashSession = currentCashSession(current);
                if (!this.cashSession) {
                    this.showErrors({ payment_method: ['Abre tu sesión de caja antes de registrar un pago en efectivo.'] });
                    return;
                }
            }
            await mutate(`/accounts-receivable/${id}/payments`, {
                amount: money(amountValue), payment_method: method,
                cash_session_id: method === 'efectivo' ? this.cashSession.id : null,
                reference: this.form.elements.reference.value.trim() || null,
            });
            this.root.querySelector('[data-receivables-live]').textContent = 'Abono registrado correctamente.';
            this.closeForm(); await this.load();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) this.showErrors(error.errors);
            else this.root.querySelector('[data-receivables-live]').textContent = message(error);
        } finally { this.submitting = false; button.disabled = false; this.form.setAttribute('aria-busy', 'false'); }
    }
}

export default function init() {
    const root = document.querySelector('[data-operative-receivables]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new ReceivablesPage(root).init();
}
