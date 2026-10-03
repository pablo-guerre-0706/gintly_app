import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { withLoading, setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { denominationTotal } from '@/core/cash-denominations';
import { money } from '@/core/money';
import { formatDateTime } from '@/dashboard/formatters';

const fmt = value => `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
let submitting = false;
let activeSession = null;

function calculate() {
    const lines = [];

    document.querySelectorAll('[data-denomination]').forEach((input) => {
        const count = input.value.replace(/\D/g, '') || '0';
        const denomination = { value: String(input.dataset.denomination), qty: count };
        const line = denominationTotal([denomination]) ?? '0.00';

        input.value = count;
        const lineTotal = input.closest('div')?.querySelector('[data-line-total]');
        if (lineTotal) lineTotal.textContent = fmt(line);
        lines.push(denomination);
    });

    const total = denominationTotal(lines) ?? '0.00';

    const countedTotal = document.querySelector('#countedTotal');
    if (countedTotal) countedTotal.textContent = fmt(total);
    return total;
}

function payload() {
    const denominations = [];

    document.querySelectorAll('[data-denomination]').forEach((input) => {
        denominations.push({
            value: String(input.dataset.denomination),
            qty: Number.parseInt(input.value || '0', 10),
        });
    });

    return {
        counted_amount: calculate(),
        counted_denominations: denominations,
        closing_notes: document.querySelector('#closingNotes')?.value.trim() || null,
    };
}

function renderResult(session) {
    const counted = session.counted_amount ?? calculate();
    const expected = session.expected_amount ?? '0.00';
    const difference = session.difference ?? '0.00';
    const unbalanced = money(difference) !== '0.00';

    document.querySelector('#reconciliationLocked')?.classList.add('hidden');
    document.querySelector('#reconciliationResult')?.classList.remove('hidden');

    const values = {
        expectedAmount: fmt(expected),
        physicalAmount: fmt(counted),
        cashDifference: fmt(difference),
    };
    Object.entries(values).forEach(([id, value]) => {
        const element = document.querySelector(`#${id}`);
        if (element) element.textContent = value;
    });

    const differenceElement = document.querySelector('#cashDifference');
    differenceElement?.classList.toggle('text-red-600', unbalanced);
    differenceElement?.classList.toggle('text-emerald-600', !unbalanced);

    const status = document.querySelector('#closingStatus');
    if (status) {
        status.textContent = unbalanced ? 'CAJA DESCUADRADA' : 'CAJA CUADRADA';
        status.className = `rounded-md px-3 py-2 text-center font-semibold ${
            unbalanced ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'
        }`;
    }

    document.querySelectorAll('#cashClosingForm input, #cashClosingForm select, #cashClosingForm textarea, #cashClosingForm button')
        .forEach((control) => { control.disabled = true; });
}

function persistedClosing(error) {
    const session = error.payload?.data ?? error.payload?.cash_session ?? null;

    return error.status === 422 && session &&
        ['descuadrada', 'cerrada'].includes(session.status);
}

async function closeCash(button) {
    if (submitting) return;

    const endpoint = activeSession ? `/cash-sessions/${activeSession.id}/close` : null;

    if (!endpoint) {
        notify({ type: 'error', message: 'Endpoint de cierre de caja no configurado.' });
        return;
    }

    submitting = true;
    setButtonLoading(button, true, { label: 'Confirmando...' });
    let completed = false;

    try {
        const send = () => api.post(endpoint, payload(), { dispatchErrors: false });
        const response = await withLoading(
            async () => {
                try { return await send(); }
                catch (error) {
                    if (!(error instanceof ApiError) || error.status !== 419) throw error;
                    await initializeCsrf({ dispatchErrors: false });
                    return send();
                }
            },
            { message: 'Procesando arqueo...' },
        );

        const session = response?.data ?? response;
        renderResult(session);
        completed = true;
        notify({ type: 'success', message: 'Caja cerrada correctamente.' });
    } catch (error) {
        if (!(error instanceof ApiError)) throw error;

        if (persistedClosing(error)) {
            renderResult(error.payload.data ?? error.payload.cash_session);
            completed = true;
            notify({ type: 'warning', message: error.message });
            return;
        }

        if (error.status === 422) {
            const message = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
            notify({ type: 'warning', message });
            return;
        }

        if (error.status === 409) {
            notify({ type: 'error', message: error.message });
            return;
        }

        if (![401, 403].includes(error.status)) throw error;
    } finally {
        setButtonLoading(button, false);
        if (completed && button) button.disabled = true;
        submitting = false;
    }
}

function renderSession(session) {
    activeSession = session;
    const context = document.querySelector('[data-cash-closing-context]');
    if (context) context.textContent = `${session.cash_register?.name ?? `Caja #${session.cash_register_id}`} · Sesión propia #${session.id}`;
    const values = {
        session: `#${session.id}`,
        register: session.cash_register?.name ?? `Caja #${session.cash_register_id}`,
        opening: fmt(session.opening_amount),
        opened: formatDateTime(session.opened_at),
    };
    Object.entries(values).forEach(([key, value]) => {
        const element = document.querySelector(`[data-cash-closing-summary="${key}"]`);
        if (element) element.textContent = value;
    });
    document.querySelector('#cashClosingRoot')?.setAttribute('aria-busy', 'false');
}

async function loadCurrentSession() {
    const root = document.querySelector('#cashClosingRoot');
    const errorBox = root?.querySelector('[data-cash-closing-error]');
    try {
        const response = await api.get('/cash-sessions/current', {}, { dispatchErrors: false });
        if (!response?.data) throw new Error('No tienes una sesión de caja abierta para cerrar.');
        renderSession(response.data);
    } catch (error) {
        if (errorBox) {
            errorBox.hidden = false;
            errorBox.textContent = error instanceof ApiError ? error.message : error.message;
        }
        root?.querySelectorAll('form input, form textarea, form button').forEach((control) => { control.disabled = true; });
        root?.setAttribute('aria-busy', 'false');
    }
}

export default function init() {
    const root = document.querySelector('#cashClosingRoot');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    root.addEventListener('input', (event) => {
        if (event.target.matches('[data-denomination]')) calculate();
    });

    const form = root.querySelector('#cashClosingForm');
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        void closeCash(form.querySelector('[data-submit]'));
    });

    calculate();
    return loadCurrentSession();
}
