import { api, ApiError } from '@/core/api-client';
import { escapeHtml } from '@/core/dom';
import { withLoading } from '@/core/loading';
import { money, cost } from '@/core/money';

let debounceTimer;

const root = () => document.querySelector('#catalogProductsRoot');
const esc = value => escapeHtml(value, '—');
const fmtMoney = value => `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
const fmtCost = value => fmtMoney(cost(String(value ?? '0')));

const typeLabel = type => ({
    simple: 'Simple',
    compound: 'Compuesto',
    service: 'Servicio',
}[type] ?? type ?? '—');

function row(product) {
    const category = product.category?.name ?? product.category_name ?? '—';
    const brand = product.brand?.name ?? product.brand_name ?? '—';
    const unit = product.unit?.abbreviation ?? product.abbreviation ?? '—';
    const tax = product.tax_class_label ?? (product.is_taxable ? 'Gravado' : 'Exento');

    return `<tr class="h-20 border-b border-neutral-200 text-xs text-neutral-600" data-product-row="${product.id}">
        <td class="px-6 text-[14px] font-bold text-[#171717]">${esc(product.sku)}</td>
        <td class="px-6">${esc(product.name)}</td><td class="px-6">${esc(category)}</td>
        <td class="px-6">${esc(brand)}</td><td class="px-6">${esc(unit)}</td>
        <td class="px-6 text-[14px] font-bold text-[#171717]">${fmtMoney(product.sale_price)}</td>
        <td class="px-6 text-[14px] font-bold text-[#333]">${fmtCost(product.cost)}</td>
        <td class="px-6"><span class="rounded bg-blue-50 px-2.5 py-2 text-blue-700 ring-1 ring-blue-600/20">${esc(typeLabel(product.type))}</span></td>
        <td class="px-6"><span class="rounded bg-amber-50 px-2.5 py-2 text-amber-700 ring-1 ring-amber-600/20">${esc(tax)}</span></td>
        <td class="px-6"><span class="rounded px-2.5 py-2 ${product.is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'} ring-1">${product.is_active ? 'Activo' : 'Inactivo'}</span></td>
    </tr>`;
}

function params() {
    return {
        search: document.querySelector('#productSearch')?.value.trim() || undefined,
        is_active: true,
        per_page: 25,
    };
}

async function loadProducts() {
    try {
        const response = await withLoading(
            () => api.get('/products', params()),
            { message: 'Cargando catálogo...' },
        );

        const products = response?.data ?? [];
        const tableBody = document.querySelector('#productsTableBody');
        const count = document.querySelector('#productsCount');

        if (tableBody) {
            tableBody.innerHTML = products.length
                ? products.map(row).join('')
                : '<tr><td colspan="10" class="py-16 text-center text-[11px] text-[#888]">No se encontraron productos.</td></tr>';
        }

        if (count) {
            count.textContent = `${response?.meta?.total ?? products.length} productos`;
        }
        const live = document.querySelector('[data-products-live]');
        if (live) live.textContent = 'Catálogo actualizado.';
    } catch (error) {
        if (error instanceof ApiError && [401, 403].includes(error.status)) return;
        const tableBody = document.querySelector('#productsTableBody');
        if (tableBody) tableBody.innerHTML = '<tr><td colspan="10" class="py-16 text-center text-[11px] text-red-700">No fue posible consultar el catálogo. Intenta nuevamente.</td></tr>';
        const live = document.querySelector('[data-products-live]');
        if (live) live.textContent = 'No fue posible consultar el catálogo.';
    } finally {
        root()?.setAttribute('aria-busy', 'false');
    }
}

export default function init() {
    const container = root();

    if (!container || container.dataset.initialized === 'true') return;

    container.dataset.initialized = 'true';
    container.addEventListener('input', (event) => {
        if (event.target.matches('#productSearch')) {
            clearTimeout(debounceTimer);
            const query = event.target.value.trim();
            if (query.length === 1) return;
            debounceTimer = setTimeout(loadProducts, 350);
        }
    });

    void loadProducts();
}
