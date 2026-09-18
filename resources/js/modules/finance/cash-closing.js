import { api, ApiError } from '@/core/api-client';
import { withLoading, setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { add, multiply, money, SCALE } from '@/core/money';

const fmt = value => `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
let submitting = false;

function calculate() {
    let total = '0.00';

    document.querySelectorAll('[data-denomination]').forEach((input) => {
        const count = input.value.replace(/\D/g, '') || '0';
        const line = multiply(String(input.dataset.denomination), count, SCALE.MONEY);

        input.value = count;
        const lineTotal = input.closest('div')?.querySelector('[data-line-total]');
        if (lineTotal) lineTotal.textContent = fmt(line);
        total = add(total, line, SCALE.MONEY);
    });

    const countedTotal = document.querySelector('#countedTotal');
    if (countedTotal) countedTotal.textContent = fmt(total);
    return total;
}

function payload() {
    const denominations = {};

    document.querySelectorAll('[data-denomination]').forEach((input) => {
        denominations[String(input.dataset.denomination)] = input.value || '0';
    });

    return {
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

    const endpoint = document.querySelector('#cashClosingRoot')?.dataset.closeUrl;

    if (!endpoint) {
        notify({ type: 'error', message: 'Endpoint de cierre de caja no configurado.' });
        return;
    }

    submitting = true;
    setButtonLoading(button, true, { label: 'Confirmando...' });
    let completed = false;

    try {
        const response = await withLoading(
            () => api.post(endpoint, payload()),
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
}
