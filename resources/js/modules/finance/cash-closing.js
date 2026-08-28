import $ from 'jquery';
import { api, ApiError } from '@/core/api-client';
import { withLoading, setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { add, multiply, money, SCALE } from '@/core/money';

const fmt = value => `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

function calculate() {
    let total = '0.00';

    $('[data-denomination]').each(function () {
        const count = this.value.replace(/\D/g, '') || '0';
        const line = multiply(String($(this).data('denomination')), count, SCALE.MONEY);

        this.value = count;
        $(this).closest('div').find('[data-line-total]').text(fmt(line));
        total = add(total, line, SCALE.MONEY);
    });

    $('#countedTotal').text(fmt(total));
    return total;
}

function payload() {
    const denominations = {};

    $('[data-denomination]').each(function () {
        denominations[String($(this).data('denomination'))] = this.value || '0';
    });

    return {
        counted_denominations: denominations,
        closing_notes: $('#closingNotes').val().trim() || null,
    };
}

function renderResult(session) {
    const counted = session.counted_amount ?? calculate();
    const expected = session.expected_amount ?? '0.00';
    const difference = session.difference ?? '0.00';
    const unbalanced = money(difference) !== '0.00';

    $('#reconciliationLocked').addClass('hidden');
    $('#reconciliationResult').removeClass('hidden');
    $('#expectedAmount').text(fmt(expected));
    $('#physicalAmount').text(fmt(counted));
    $('#cashDifference').text(fmt(difference))
        .toggleClass('text-red-600', unbalanced)
        .toggleClass('text-emerald-600', !unbalanced);
    $('#closingStatus')
        .text(unbalanced ? 'CAJA DESCUADRADA' : 'CAJA CUADRADA')
        .attr('class', `rounded-md px-3 py-2 text-center font-semibold ${
            unbalanced ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'
        }`);

    $('#cashClosingForm :input').prop('disabled', true);
}

function persistedClosing(error) {
    const session = error.payload?.data ?? error.payload?.cash_session ?? null;

    return error.status === 422 && session &&
        ['descuadrada', 'cerrada'].includes(session.status);
}

async function closeCash(button) {
    const endpoint = $('#cashClosingRoot').data('close-url');

    if (!endpoint) {
        notify({ type: 'error', message: 'Endpoint de cierre de caja no configurado.' });
        return;
    }

    setButtonLoading(button, true, { label: 'Confirmando...' });

    try {
        const response = await withLoading(
            () => api.post(endpoint, payload()),
            { message: 'Procesando arqueo...' },
        );

        const session = response?.data ?? response;
        renderResult(session);
        notify({ type: 'success', message: 'Caja cerrada correctamente.' });
    } catch (error) {
        if (!(error instanceof ApiError)) throw error;

        if (persistedClosing(error)) {
            renderResult(error.payload.data ?? error.payload.cash_session);
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
    }
}

export default function init() {
    if (!$('#cashClosingRoot').length) return;

    $(document)
        .on('input', '[data-denomination]', calculate)
        .on('submit', '#cashClosingForm', function (event) {
            event.preventDefault();
            closeCash($(this).find('[data-submit]').get(0));
        });

    calculate();
}
