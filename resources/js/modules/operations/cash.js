import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { formatDateTime, formatMoney } from '@/dashboard/formatters';
import { compare } from '@/core/money';
import { getSessionContext } from '@/core/session-context';

const MONEY_PATTERN = /^\d+(?:\.\d{1,2})?$/;

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
    if (error.status === 403) return 'No tienes autorización para operar esta caja.';
    if (error.status === 409 || error.status === 422) return error.message;
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo completar la operación.';
    return error.message;
}

class CashPage {
    constructor(root) {
        this.root = root;
        this.session = null;
        this.context = null;
        this.pending = false;
    }

    async init() {
        this.context = await getSessionContext();
        if (this.context.role !== 'ROL-03' || !this.context.profiles.includes('cajero')) {
            throw new TypeError('El contexto no autoriza la operación de caja.');
        }
        this.root.querySelector('[data-cash-movement-form]').hidden = !this.context.capabilities.includes('caja.movimiento.crear');
        this.root.querySelector('[data-cash-close-link]').hidden = !this.context.capabilities.includes('caja.cerrar');
        this.root.querySelector('[data-cash-open-form]').addEventListener('submit', (event) => { event.preventDefault(); void this.open(); });
        this.root.querySelector('[data-cash-movement-form]').addEventListener('submit', (event) => { event.preventDefault(); void this.movement(); });
        this.root.querySelector('[name="category"]').addEventListener('change', (event) => {
            this.root.querySelector('[data-cash-movement-type-row]').hidden = event.target.value !== 'ajuste';
        });
        this.root.querySelector('[data-cash-refresh]').addEventListener('click', () => this.loadMovements());
        this.root.addEventListener('click', (event) => {
            if (event.target.closest('[data-cash-retry]')) void this.load();
        });
        return this.load();
    }

    setState(text, error = false) {
        const state = this.root.querySelector('[data-cash-page-state]');
        state.hidden = false;
        state.className = `rounded-2xl border p-6 text-sm ${error ? 'border-red-200 bg-red-50 text-red-800' : 'border-slate-200 bg-white text-gintly-text-secondary'}`;
        state.replaceChildren(document.createTextNode(text));
        if (error) {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.dataset.cashRetry = '';
            retry.className = 'mt-4 block min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold';
            retry.textContent = 'Reintentar';
            state.appendChild(retry);
        }
    }

    async load() {
        this.root.setAttribute('aria-busy', 'true');
        this.root.querySelector('[data-cash-open-form]').hidden = true;
        this.root.querySelector('[data-cash-active]').hidden = true;
        this.setState('Consultando tu sesión activa…');
        try {
            const response = await api.get('/cash-sessions/current', {}, { dispatchErrors: false });
            if (!response || typeof response !== 'object' || !('data' in response)
                || (response.data !== null && !Number.isInteger(response.data?.id))) {
                throw new TypeError('El servidor no devolvió un estado de caja válido.');
            }
            this.session = response.data;
            if (this.session) this.renderSession();
            else if (this.context.capabilities.includes('caja.abrir')) await this.renderOpening();
            else this.setState('No tienes una sesión abierta ni capacidad para abrir una caja.');
        } catch (error) { this.setState(message(error), true); }
        finally { this.root.setAttribute('aria-busy', 'false'); }
    }

    async renderOpening() {
        const response = await api.get('/cash-registers', { is_active: true, per_page: 100 }, { dispatchErrors: false });
        if (!Array.isArray(response?.data)) throw new TypeError('El servidor no devolvió cajas válidas.');
        const registers = response.data;
        const select = this.root.querySelector('[name="cash_register_id"]');
        const prompt = document.createElement('option');
        prompt.value = '';
        prompt.textContent = registers.length ? 'Selecciona una caja' : 'No hay cajas activas en tu sucursal';
        select.replaceChildren(prompt, ...registers.map((register) => {
            const option = document.createElement('option');
            option.value = String(register.id);
            option.textContent = register.name;
            return option;
        }));
        select.disabled = registers.length === 0;
        this.root.querySelector('[data-cash-page-state]').hidden = true;
        this.root.querySelector('[data-cash-open-form]').hidden = false;
    }

    renderSession() {
        this.root.querySelector('[data-cash-page-state]').hidden = true;
        this.root.querySelector('[data-cash-active]').hidden = false;
        this.root.querySelector('[data-cash-session-id]').textContent = `#${this.session.id}`;
        this.root.querySelector('[data-cash-register-name]').textContent = this.session.cash_register?.name ?? `Caja #${this.session.cash_register_id}`;
        this.root.querySelector('[data-cash-opening]').textContent = formatMoney(this.session.opening_amount);
        this.root.querySelector('[data-cash-opened-at]').textContent = formatDateTime(this.session.opened_at);
        void this.loadMovements();
    }

    clearErrors() { this.root.querySelectorAll('[data-cash-error]').forEach((element) => { element.textContent = ''; }); }

    showErrors(errors) {
        let first = null;
        Object.entries(errors ?? {}).forEach(([field, values]) => {
            const key = field.split('.')[0];
            const output = this.root.querySelector(`[data-cash-error="${key}"]`);
            const control = this.root.querySelector(`[name="${key}"]`);
            if (output) output.textContent = Array.isArray(values) ? values[0] : String(values);
            first ??= control;
        });
        first?.focus();
    }

    async open() {
        if (this.pending) return;
        this.clearErrors();
        const form = this.root.querySelector('[data-cash-open-form]');
        const data = new FormData(form);
        const amount = String(data.get('opening_amount') ?? '').trim();
        if (!MONEY_PATTERN.test(amount)) return this.showErrors({ opening_amount: ['Usa un monto no negativo con máximo dos decimales.'] });
        const button = form.querySelector('[data-cash-open-submit]');
        this.pending = true; button.disabled = true; form.setAttribute('aria-busy', 'true');
        try {
            await mutate('/cash-sessions', { cash_register_id: Number(data.get('cash_register_id')), opening_amount: amount });
            this.root.querySelector('[data-cash-live]').textContent = 'Sesión de caja abierta.';
            form.reset();
            await this.load();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) this.showErrors(error.errors);
            else this.setState(message(error), true);
        } finally { this.pending = false; button.disabled = false; form.setAttribute('aria-busy', 'false'); }
    }

    async movement() {
        if (this.pending || !this.session) return;
        this.clearErrors();
        const form = this.root.querySelector('[data-cash-movement-form]');
        const data = new FormData(form);
        const amount = String(data.get('amount') ?? '').trim();
        if (!MONEY_PATTERN.test(amount) || compare(amount, '0') <= 0) return this.showErrors({ amount: ['Usa un monto mayor que cero con máximo dos decimales.'] });
        const category = String(data.get('category'));
        const type = category === 'retiro' ? 'egreso' : String(data.get('type'));
        const button = form.querySelector('[data-cash-movement-submit]');
        this.pending = true; button.disabled = true; form.setAttribute('aria-busy', 'true');
        try {
            await mutate('/cash-movements', {
                cash_session_id: this.session.id,
                type,
                category,
                payment_method: 'efectivo',
                amount,
                description: String(data.get('description') ?? '').trim() || null,
            });
            form.reset();
            this.root.querySelector('[data-cash-movement-type-row]').hidden = true;
            this.root.querySelector('[data-cash-live]').textContent = 'Movimiento registrado.';
            await this.loadMovements();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) this.showErrors(error.errors);
            else this.root.querySelector('[data-cash-live]').textContent = message(error);
        } finally { this.pending = false; button.disabled = false; form.setAttribute('aria-busy', 'false'); }
    }

    async loadMovements() {
        if (!this.session) return;
        const state = this.root.querySelector('[data-cash-movements-state]');
        const list = this.root.querySelector('[data-cash-movements]');
        state.hidden = false; state.textContent = 'Cargando movimientos…'; list.hidden = true;
        try {
            const response = await api.get(`/cash-sessions/${this.session.id}/movements`, { per_page: 25 }, { dispatchErrors: false });
            if (!Array.isArray(response?.data)) throw new TypeError('El servidor no devolvió movimientos válidos.');
            const movements = response.data;
            if (!movements.length) { state.textContent = 'Todavía no hay movimientos en esta sesión.'; return; }
            list.replaceChildren(...movements.map((movement) => {
                const item = document.createElement('li');
                const description = document.createElement('div');
                const amount = document.createElement('strong');
                item.className = 'flex items-start justify-between gap-4 p-5 text-sm';
                description.className = 'min-w-0';
                description.textContent = `${movement.category_label} · ${movement.payment_method_label}`;
                amount.textContent = `${movement.type === 'egreso' ? '−' : '+'}${formatMoney(movement.amount)}`;
                amount.className = movement.type === 'egreso' ? 'text-red-700' : 'text-emerald-700';
                item.append(description, amount);
                return item;
            }));
            state.hidden = true; list.hidden = false;
            this.root.querySelector('[data-cash-movements-summary]').textContent = `${response.meta?.total ?? movements.length} movimientos`;
        } catch (error) { state.textContent = message(error); }
    }
}

export default function init() {
    const root = document.querySelector('[data-operator-cash]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new CashPage(root).init();
}
