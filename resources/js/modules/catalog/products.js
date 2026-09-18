import { api, ApiError } from '@/core/api-client';
import { escapeHtml } from '@/core/dom';
import { withLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { money, cost } from '@/core/money';

let debounceTimer;
let activeCategory = '';

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
    const tax = product.is_taxable ? root()?.dataset.taxLabel : 'Exento';

    return `<tr class="h-20 border-b border-neutral-200 text-xs text-neutral-600" data-product-row="${product.id}">
        <td class="px-6 text-[14px] font-bold text-[#171717]">${esc(product.sku)}</td>
        <td class="px-6">${esc(product.name)}</td><td class="px-6">${esc(category)}</td>
        <td class="px-6">${esc(brand)}</td><td class="px-6">${esc(unit)}</td>
        <td class="px-6 text-[14px] font-bold text-[#171717]">${fmtMoney(product.sale_price)}</td>
        <td class="px-6 text-[14px] font-bold text-[#333]">${fmtCost(product.cost)}</td>
        <td class="px-6"><span class="rounded bg-blue-50 px-2.5 py-2 text-blue-700 ring-1 ring-blue-600/20">${esc(typeLabel(product.type))}</span></td>
        <td class="px-6"><span class="rounded bg-amber-50 px-2.5 py-2 text-amber-700 ring-1 ring-amber-600/20">${esc(tax)}</span></td>
        <td class="px-6"><span class="rounded px-2.5 py-2 ${product.is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'} ring-1">${product.is_active ? 'Activo' : 'Inactivo'}</span></td>
        <td class="px-6"><button type="button" data-edit-product="${product.id}" class="h-9 rounded-md border border-cyan-800 px-3 text-xs font-medium text-cyan-900">Editar</button></td>
    </tr>`;
}

function params() {
    return {
        search: document.querySelector('#productSearch')?.value.trim() || undefined,
        category_id: activeCategory || undefined,
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
                : '<tr><td colspan="11" class="py-16 text-center text-[11px] text-[#888]">No se encontraron productos.</td></tr>';
        }

        if (count) {
            count.textContent = `${response?.meta?.total ?? products.length} productos`;
        }
    } catch (error) {
        if (error instanceof ApiError && [401, 403].includes(error.status)) return;
        notify({ type: 'error', message: 'No fue posible consultar el catálogo.' });
    }
}

async function exportProducts() {
    const endpoint = root()?.dataset.exportUrl;

    if (!endpoint) {
        notify({ type: 'warning', message: 'Endpoint de exportación no configurado.' });
        return;
    }

    const response = await withLoading(
        () => api.get(endpoint, params()),
        { message: 'Preparando archivo Excel...' },
    );

    const url = response?.data?.url ?? response?.url;
    if (url) window.location.assign(url);
}

export default function init() {
    const container = root();

    if (!container || container.dataset.initialized === 'true') return;

    container.dataset.initialized = 'true';
    container.addEventListener('input', (event) => {
        if (event.target.matches('#productSearch')) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadProducts, 350);
        }
    });

    container.addEventListener('click', (event) => {
        const category = event.target.closest('[data-category-filter]');
        const edit = event.target.closest('[data-edit-product]');

        if (category && container.contains(category)) {
            activeCategory = category.dataset.categoryFilter ?? '';
            container.querySelectorAll('[data-category-filter]').forEach((button) => {
                button.classList.remove('border-[#087F98]', 'bg-[#087F98]', 'text-white');
            });
            category.classList.add('border-[#087F98]', 'bg-[#087F98]', 'text-white');
            void loadProducts();
            return;
        }

        if (edit && container.contains(edit)) {
            document.dispatchEvent(new CustomEvent('gintly:product-edit', {
                detail: { id: Number(edit.dataset.editProduct) },
            }));
            return;
        }

        if (event.target.closest('[data-export]')) {
            void exportProducts();
        }
    });
}
