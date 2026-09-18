import { api, ApiError } from '@/core/api-client';
import { withLoading, setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { subtract, quantity, cost, money, SCALE } from '@/core/money';

let timer;
let activeFilter = 'all';

const fmtMoney = value =>
    `C$ ${money(cost(String(value ?? '0'))).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

function refreshMath() {
    document.querySelectorAll('[data-reconciliation-row]').forEach((row) => {
        const system = quantity(String(row.dataset.system));
        const counted = quantity(String(row.dataset.counted));
        const difference = subtract(counted, system, SCALE.QUANTITY);

        const differenceElement = row.querySelector('[data-difference]');
        if (differenceElement) {
            differenceElement.textContent = difference;
            differenceElement.classList.toggle('bg-red-100', difference.startsWith('-'));
            differenceElement.classList.toggle('text-red-700', difference.startsWith('-'));
            differenceElement.classList.toggle('bg-emerald-100', !difference.startsWith('-'));
            differenceElement.classList.toggle('text-emerald-700', !difference.startsWith('-'));
        }

        const costDisplay = row.querySelector('[data-cost-display]');
        if (costDisplay) costDisplay.textContent = fmtMoney(row.dataset.cost);
    });
}

function applyFilters() {
    const query = document.querySelector('#inventorySearch')?.value.trim().toLowerCase() ?? '';

    document.querySelectorAll('[data-reconciliation-row]').forEach((row) => {
        const matchesText = !row.dataset.name || row.dataset.name.includes(query);
        const matchesLevel = activeFilter === 'all' || row.dataset.level === activeFilter;

        row.hidden = !(matchesText && matchesLevel);
    });
}

async function applyPhysicalCount(id, button) {
    setButtonLoading(button, true, { label: 'Ajustando...' });
    let applied = false;

    try {
        const response = await withLoading(
            () => api.post(`/physical-counts/${id}/apply`, {}),
            { message: 'Aplicando ajuste de inventario...' },
        );

        notify({ type: 'success', message: 'Conteo aplicado y stock conciliado correctamente.' });
        applied = true;
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
        if (applied && button) {
            button.disabled = true;
            button.textContent = 'Ajustado';
        }
    }
}

async function exportInventory() {
    const endpoint = document.querySelector('#inventoryReconciliationRoot')?.dataset.exportUrl;

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
    const root = document.querySelector('#inventoryReconciliationRoot');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    refreshMath();

    root.addEventListener('input', (event) => {
        if (event.target.matches('#inventorySearch')) {
            clearTimeout(timer);
            timer = setTimeout(applyFilters, 300);
        }
    });

    root.addEventListener('click', (event) => {
        const filter = event.target.closest('[data-stock-filter]');
        const apply = event.target.closest('[data-apply-count]');

        if (filter && root.contains(filter)) {
            activeFilter = filter.dataset.stockFilter;
            root.querySelectorAll('[data-stock-filter]').forEach((button) => {
                button.classList.remove('border-[#087F98]', 'bg-[#087F98]', 'text-white');
            });
            filter.classList.add('border-[#087F98]', 'bg-[#087F98]', 'text-white');
            applyFilters();
            return;
        }

        if (apply && root.contains(apply)) {
            void applyPhysicalCount(apply.dataset.applyCount, apply);
            return;
        }

        if (event.target.closest('[data-export]')) {
            void exportInventory();
        }
    });
}
