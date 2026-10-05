import { ApiError } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { formatDateTime } from '@/dashboard/formatters';
import { cashBusy, cashError, cashFieldErrors, cashNotice, cashPost, cashRead } from '@/modules/operations/cash-shared';

const RATE = /^\d+(?:\.\d{1,6})?$/;

class ExchangeRates {
    constructor(root) { this.root = root; this.form = root.querySelector('[data-rate-form]'); this.pending = false; this.loading = false; this.context = null; }

    async init() {
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        this.root.querySelector('[data-rate-retry]').addEventListener('click', () => { void this.load(); });
        try {
            this.context = await getSessionContext();
            if (!['ROL-01', 'ROL-02'].includes(this.context.role) || !this.context.capabilities.includes('caja.gestionar')) throw new TypeError('Esta vista requiere administración de caja.');
            await this.load();
        } catch (error) { cashNotice(this.root, cashError(error), true); this.root.querySelector('[data-rate-current]').textContent = 'Tasa no disponible.'; }
        finally { this.root.setAttribute('aria-busy', 'false'); }
    }

    async load() {
        if (this.loading) return;
        this.loading = true; this.root.querySelector('[data-rate-state]').textContent = 'Cargando…'; this.root.querySelector('[data-rate-retry]').hidden = true;
        try {
            const response = await cashRead('/exchange-rates', { currency: 'USD' });
            if (!Array.isArray(response?.data)) throw new TypeError('El servidor no devolvió el historial de tasas.');
            if (response.data.some((item) => item.currency !== 'USD' || typeof item.rate !== 'string' || !item.effective_from)) throw new TypeError('El historial contiene una tasa incompatible.');
            const list = this.root.querySelector('[data-rate-list]'); list.replaceChildren();
            const now = Date.now();
            const current = response.data.filter((item) => new Date(item.effective_from).getTime() <= now).sort((a, b) => new Date(b.effective_from) - new Date(a.effective_from))[0];
            this.root.querySelector('[data-rate-current]').textContent = current ? `Tasa USD vigente: ${current.rate} NIO por 1 USD · desde ${formatDateTime(current.effective_from, this.context.business.timezone)}.` : 'No hay tasa USD vigente. Las vigencias futuras todavía no se aplican.';
            response.data.forEach((item) => {
                const row = document.createElement('li'); row.className = 'py-4 text-sm';
                const title = document.createElement('p'); title.className = 'font-semibold'; title.textContent = `${item.rate} NIO / 1 USD`;
                const detail = document.createElement('p'); detail.className = 'mt-1 text-gintly-text-secondary'; detail.textContent = `Desde ${formatDateTime(item.effective_from, this.context.business.timezone)} · ${item.created_by_name ?? `Usuario #${item.created_by}`}`;
                row.append(title, detail); list.append(row);
            });
            this.root.querySelector('[data-rate-state]').textContent = response.data.length ? `${response.data.length} vigencia(s) registradas.` : 'No hay tasas registradas.';
        } catch (error) { this.root.querySelector('[data-rate-state]').textContent = cashError(error); this.root.querySelector('[data-rate-retry]').hidden = false; }
        finally { this.loading = false; }
    }

    async submit() {
        if (this.pending) return;
        cashFieldErrors(this.form);
        const rate = this.form.elements.namedItem('rate').value.trim();
        const localDate = this.form.elements.namedItem('effective_from').value;
        const fieldError = this.root.querySelector('[data-cash-field-error]'); fieldError.hidden = true;
        if (!RATE.test(rate) || /^0(?:\.0+)?$/.test(rate)) { this.form.elements.namedItem('rate').focus(); fieldError.textContent = 'La tasa debe ser positiva y tener máximo seis decimales.'; fieldError.hidden = false; return; }
        const date = new Date(localDate);
        if (!localDate || Number.isNaN(date.getTime())) { this.form.elements.namedItem('effective_from').focus(); fieldError.textContent = 'Indica la fecha y hora de vigencia.'; fieldError.hidden = false; return; }
        this.pending = true; cashBusy(this.form, true, 'Registrando…');
        try {
            const response = await cashPost('/exchange-rates', { currency: 'USD', rate, effective_from: date.toISOString() }, 201);
            if (response?.data?.currency !== 'USD' || !Number.isInteger(response.data.id)) throw new TypeError('No se confirmó la tasa. Consulta el historial antes de repetirla.');
            cashNotice(this.root, `Tasa #${response.data.id} registrada; aplica desde ${formatDateTime(response.data.effective_from, this.context.business.timezone)}.`, false);
            this.form.reset(); void this.load();
        } catch (error) { if (error instanceof ApiError && error.status === 422) cashFieldErrors(this.form, error.errors); cashNotice(this.root, cashError(error), true); }
        finally { this.pending = false; cashBusy(this.form, false); }
    }
}

export default function init() {
    const root = document.querySelector('[data-exchange-rates]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; void new ExchangeRates(root).init();
}
