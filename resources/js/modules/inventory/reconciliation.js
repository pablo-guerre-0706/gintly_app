import $ from 'jquery';
import { api, ApiError } from '@/core/api-client';
import { withLoading, setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { subtract, quantity, cost, money, SCALE } from '@/core/money';

let timer;
let activeFilter = 'all';

const fmtMoney = value =>
    `C$ ${money(cost(String(value ?? '0'))).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

function refreshMath() {
    $('[data-reconciliation-row]').each(function () {
        const $row = $(this);
        const system = quantity(String($row.data('system')));
        const counted = quantity(String($row.data('counted')));
        const difference = subtract(counted, system, SCALE.QUANTITY);

        $row.find('[data-difference]')
            .text(difference)
            .toggleClass('bg-red-100 text-red-700', difference.startsWith('-'))
            .toggleClass('bg-emerald-100 text-emerald-700', !difference.startsWith('-'));

        $row.find('[data-cost-display]').text(fmtMoney($row.data('cost')));
    });
}

function applyFilters() {
    const query = $('#inventorySearch').val().trim().toLowerCase();

    $('[data-reconciliation-row]').each(function () {
        const $row = $(this);
        const matchesText = !$row.data('name') || String($row.data('name')).includes(query);
        const matchesLevel = activeFilter === 'all' || $row.data('level') === activeFilter;

        $row.toggle(matchesText && matchesLevel);
    });
}

async function applyPhysicalCount(id, button) {
    setButtonLoading(button, true, { label: 'Ajustando...' });

    try {
        const response = await withLoading(
            () => api.post(`/physical-counts/${id}/apply`, {}),
            { message: 'Aplicando ajuste de inventario...' },
        );

        notify({ type: 'success', message: 'Conteo aplicado y stock conciliado correctamente.' });
        $(button).prop('disabled', true).text('Ajustado');
        document.dispatchEvent(new CustomEvent('gintly:physical-count-applied', { detail: response?.data ?? response }));
    } catch (error) {
        if (!(error instanceof ApiError)) throw error;

        if (error.status === 409) {
            notify({ type: 'warning', message: error.message });
            return;
        }

        if (error.status === 422) {
            const message = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
            notify({ type: 'warning', message });
            return;
        }

        if (![401, 403].includes(error.status)) throw error;
    } finally {
        setButtonLoading(button, false);
    }
}

async function exportInventory() {
    const endpoint = $('#inventoryReconciliationRoot').data('export-url');

    if (!endpoint) {
        notify({ type: 'warning', message: 'Endpoint de exportación no configurado.' });
        return;
    }

    const response = await withLoading(
        () => api.get(endpoint),
        { message: 'Preparando archivo Excel...' },
    );

    const url = response?.data?.url ?? response?.url;
    if (url) window.location.assign(url);
}

export default function init() {
    const $root = $('#inventoryReconciliationRoot');
    if (!$root.length) return;

    refreshMath();

    $root
        .on('input', '#inventorySearch', function () {
            clearTimeout(timer);
            timer = setTimeout(applyFilters, 300);
        })
        .on('click', '[data-stock-filter]', function () {
            activeFilter = String($(this).data('stock-filter'));
            $('[data-stock-filter]').removeClass('border-[#087F98] bg-[#087F98] text-white');
            $(this).addClass('border-[#087F98] bg-[#087F98] text-white');
            applyFilters();
        })
        .on('click', '[data-apply-count]', function () {
            applyPhysicalCount($(this).data('apply-count'), this);
        })
        .on('click', '[data-export]', exportInventory);
}
