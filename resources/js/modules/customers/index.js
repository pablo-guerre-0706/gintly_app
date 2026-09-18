import { api, ApiError } from '@/core/api-client';
import { escapeHtml } from '@/core/dom';
import { withLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { money, subtract } from '@/core/money';

let debounceTimer;
const esc = value => escapeHtml(value, '—');
const fmt = value => `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

function customerFrom(card) {
    try { return JSON.parse(card.dataset.customer); }
    catch { return null; }
}

function cardTemplate(c) {
    const type = c.profile_type ?? 'occasional';
    return `<article tabindex="0" data-customer-card data-type="${esc(type)}"
        data-customer="${esc(JSON.stringify(c))}"
        class="grid cursor-pointer gap-4 rounded-[14px] border border-[#D6D6D6] bg-white px-5 py-5 transition hover:border-[#9ABFC8] hover:shadow-sm sm:grid-cols-[1.35fr_1fr_110px]">
        <div><h2 class="truncate text-[12px] font-bold">${esc(c.name)}</h2>
        <span class="mt-2 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-[8px] text-blue-700">${esc(c.profile_label ?? 'Cliente')}</span>
        <p class="mt-3 text-[8px] text-[#696969]">Cédula: ${esc(c.document_number)}</p>
        <p class="mt-2 truncate text-[8px] text-[#777]">Dirección: ${esc(c.address ?? '—')}</p></div>
        <dl class="space-y-2 pt-1 text-[8px] text-[#777]">
        <div><b>Número celular:</b> ${esc(c.phone_number)}</div>
        <div><b>Límite de crédito:</b> ${fmt(c.credit_limit)}</div></dl>
        <div class="text-right"><p class="text-[19px] font-bold">${fmt(c.balance)}</p>
        <p class="mt-2 text-[10px] text-[#777]">${esc(c.purchase_count ?? 0)} compras</p></div></article>`;
}

function renderCustomers(customers = []) {
    const list = document.querySelector('#customersList');

    if (list) {
        list.innerHTML = customers.length
            ? customers.map(cardTemplate).join('')
            : '<p class="py-16 text-center text-[10px] text-[#888]">No se encontraron clientes.</p>';
    }
}

function renderDetail(customer) {
    const available = subtract(
        String(customer.credit_limit ?? '0'),
        String(customer.balance ?? '0'),
    );

    const detail = document.querySelector('#customerDetail');

    if (!detail) return;

    detail.innerHTML = `
        <div class="w-full max-w-xs text-left">
            <div class="border-b border-[#E8E8E8] pb-5">
                <p class="text-[17px] font-bold text-[#202020]">${esc(customer.name)}</p>
                <p class="mt-2 text-[9px] text-[#777]">${esc(customer.document_number ?? 'Sin documento')}</p>
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-4 text-[9px]">
                <div><dt class="text-[#888]">Número celular</dt><dd class="mt-1 font-semibold">${esc(customer.phone_number)}</dd></div>
                <div><dt class="text-[#888]">Compras</dt><dd class="mt-1 font-semibold">${esc(customer.purchase_count ?? 0)}</dd></div>
                <div><dt class="text-[#888]">Límite de crédito</dt><dd class="mt-1 font-semibold">${fmt(customer.credit_limit)}</dd></div>
                <div><dt class="text-[#888]">Saldo adeudado</dt><dd class="mt-1 font-semibold text-red-600">${fmt(customer.balance)}</dd></div>
            </dl>
            <div class="mt-5 rounded-[10px] bg-[#F3F8F9] p-4">
                <p class="text-[8px] text-[#718087]">Crédito disponible</p>
                <p class="mt-1 text-[18px] font-bold text-[#087F98]">${fmt(available)}</p>
            </div>
            <p class="mt-5 text-[9px] leading-5 text-[#777]">${esc(customer.address ?? 'Sin dirección registrada')}</p>
        </div>`;
}

async function searchCustomers(search) {
    const endpoint = document.querySelector('#customersRoot')?.dataset.customersUrl;

    if (!endpoint) {
        notify({ type: 'error', message: 'Endpoint de clientes no configurado.' });
        return;
    }

    try {
        const response = await withLoading(
            () => api.get(endpoint, { search, per_page: 25 }),
            { message: 'Buscando clientes...' },
        );
        renderCustomers(response?.data ?? []);
    } catch (error) {
        if (error instanceof ApiError && [401, 403].includes(error.status)) return;
        notify({ type: 'error', message: 'No fue posible consultar los clientes.' });
    }
}

export default function init() {
    const root = document.querySelector('#customersRoot');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';

    const selectCustomer = (event) => {
        const card = event.target.closest('[data-customer-card]');

        if (!card || !root.contains(card)) return;
        if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;

        event.preventDefault();
        root.querySelectorAll('[data-customer-card]').forEach((element) => {
            element.classList.remove('border-[#087F98]', 'bg-[#F8FCFD]', 'ring-1', 'ring-[#087F98]/20');
        });
        card.classList.add('border-[#087F98]', 'bg-[#F8FCFD]', 'ring-1', 'ring-[#087F98]/20');

        const customer = customerFrom(card);
        if (customer) renderDetail(customer);
    };

    root.addEventListener('click', (event) => {
        const filter = event.target.closest('[data-profile-filter]');

        if (filter && root.contains(filter)) {
            root.querySelectorAll('[data-profile-filter]').forEach((button) => {
                button.classList.remove('border-[#087F98]', 'bg-[#087F98]', 'text-white');
            });
            filter.classList.add('border-[#087F98]', 'bg-[#087F98]', 'text-white');

            const type = filter.dataset.profileFilter;
            root.querySelectorAll('[data-customer-card]').forEach((card) => {
                card.hidden = type !== 'all' && card.dataset.type !== type;
            });
            return;
        }

        selectCustomer(event);
    });

    root.addEventListener('keydown', selectCustomer);
    root.addEventListener('input', (event) => {
        if (event.target.matches('#customerSearch')) {
            clearTimeout(debounceTimer);
            const query = event.target.value.trim();
            debounceTimer = setTimeout(() => searchCustomers(query), 350);
        }
    });
}
