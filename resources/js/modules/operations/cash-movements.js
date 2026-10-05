import { ApiError } from '@/core/api-client';
import { formatDateTime } from '@/dashboard/formatters';
import { cashierContext, cashAmount, cashBusy, cashError, cashFieldErrors, cashLabel, cashNotice, cashPost, cashRead, currentCashSession } from './cash-shared';

class CashMovements {
    constructor(root) { this.root = root; this.form = root.querySelector('[data-cash-form]'); this.session = null; this.context = null; this.page = 1; this.lastPage = 1; this.pending = false; this.loading = false; }

    async init() {
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        this.form.elements.namedItem('category').addEventListener('change', () => this.syncCategory());
        this.form.elements.namedItem('type').addEventListener('change', () => this.syncCategory());
        this.root.querySelector('[data-cash-retry]').addEventListener('click', () => { void this.loadList(); });
        this.root.querySelector('[data-cash-prev]').addEventListener('click', () => { this.page -= 1; void this.loadList(); });
        this.root.querySelector('[data-cash-next]').addEventListener('click', () => { this.page += 1; void this.loadList(); });
        this.syncCategory();
        try {
            this.context = await cashierContext('caja.movimiento.crear');
            this.session = await currentCashSession(this.context);
            this.root.querySelector('[data-cash-loading]').hidden = true;
            if (!this.session) { this.root.querySelector('[data-cash-empty]').hidden = false; return; }
            this.root.querySelector('[data-cash-content]').hidden = false;
            this.root.querySelector('[data-cash-session]').textContent = `Sesión #${this.session.id} · ${this.session.cash_register.name} · Sucursal #${this.context.branch.id}`;
            await this.loadList();
        } catch (error) { this.root.querySelector('[data-cash-loading]').hidden = true; cashNotice(this.root, cashError(error), true); }
        finally { this.root.setAttribute('aria-busy', 'false'); }
    }

    syncCategory() {
        const category = this.form.elements.namedItem('category'); const type = this.form.elements.namedItem('type');
        const retire = category.querySelector('option[value="retiro"]');
        retire.disabled = type.value !== 'egreso';
        if (type.value === 'ingreso' && category.value === 'retiro') category.value = 'ajuste';
    }

    async loadList() {
        if (this.loading || !this.session) return;
        this.loading = true;
        const state = this.root.querySelector('[data-cash-list-state]'); state.textContent = 'Cargando movimientos…';
        this.root.querySelector('[data-cash-retry]').hidden = true;
        try {
            const response = await cashRead(`/cash-sessions/${this.session.id}/movements`, { page: this.page, per_page: 15 });
            if (!Array.isArray(response?.data) || !Number.isInteger(response?.meta?.last_page)) throw new TypeError('El servidor no devolvió movimientos paginados válidos.');
            this.lastPage = response.meta.last_page;
            const list = this.root.querySelector('[data-cash-list]'); list.replaceChildren();
            response.data.forEach((movement) => {
                const row = document.createElement('li'); row.className = 'py-4';
                const title = document.createElement('p'); title.className = 'font-semibold'; title.textContent = `${movement.category_label} · ${cashLabel(movement.amount, movement.currency)}`;
                const meta = document.createElement('p'); meta.className = 'mt-1 text-xs leading-5 text-gintly-text-secondary'; meta.textContent = `${movement.type_label} · ${movement.payment_method_label} · ${formatDateTime(movement.created_at, this.context.business.timezone)}${movement.exchange_rate ? ` · tasa snapshot ${movement.exchange_rate}` : ''}${movement.base_amount ? ` · base ${cashLabel(movement.base_amount)}` : ''}`;
                row.append(title, meta); list.append(row);
            });
            state.textContent = response.data.length ? `${response.meta.total} movimiento(s) registrados. El historial no admite edición.` : 'No hay movimientos en esta sesión.';
            this.root.querySelector('[data-cash-page]').textContent = `Página ${response.meta.current_page} de ${response.meta.last_page}`;
            this.root.querySelector('[data-cash-prev]').disabled = this.page <= 1;
            this.root.querySelector('[data-cash-next]').disabled = this.page >= this.lastPage;
        } catch (error) { state.textContent = cashError(error); this.root.querySelector('[data-cash-retry]').hidden = false; }
        finally { this.loading = false; }
    }

    async submit() {
        if (this.pending || !this.session) return;
        cashFieldErrors(this.form);
        const fields = this.form.elements;
        const amount = cashAmount(fields.namedItem('amount').value);
        const fieldError = this.form.querySelector('[data-cash-field-error]'); fieldError.hidden = true;
        if (amount === null || amount === '0.00') { fields.namedItem('amount').focus(); fieldError.textContent = 'Ingresa un monto positivo con máximo dos decimales.'; fieldError.hidden = false; return; }
        const payload = { cash_session_id: this.session.id, type: fields.namedItem('type').value, category: fields.namedItem('category').value, payment_method: fields.namedItem('payment_method').value, currency: fields.namedItem('currency').value, amount, description: fields.namedItem('description').value.trim() || null };
        this.pending = true; cashBusy(this.form, true, 'Registrando…');
        try {
            const response = await cashPost('/cash-movements', payload, 201);
            if (!Number.isInteger(response?.data?.id) || response.data.cash_session_id !== this.session.id) throw new TypeError('No se confirmó el movimiento. Revisa el historial antes de repetirlo.');
            const movement = response.data;
            cashNotice(this.root, `Movimiento #${movement.id} registrado: ${cashLabel(movement.amount, movement.currency)}${movement.exchange_rate ? ` · tasa snapshot ${movement.exchange_rate}` : ''}.`, false);
            this.form.reset(); this.syncCategory(); this.page = 1; void this.loadList();
        } catch (error) { if (error instanceof ApiError && error.status === 422) cashFieldErrors(this.form, error.errors); cashNotice(this.root, cashError(error), true); }
        finally { this.pending = false; cashBusy(this.form, false); }
    }
}

export default function init() {
    const root = document.querySelector('[data-cash-movements]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; void new CashMovements(root).init();
}
