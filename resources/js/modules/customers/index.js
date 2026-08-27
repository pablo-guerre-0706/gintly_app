import $ from 'jquery';
import { api, ApiError } from '@/core/api-client';
import { withLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { money, subtract } from '@/core/money';

let debounceTimer;
const esc = value => $('<div>').text(value ?? '—').html();
const fmt = value => C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')};

function customerFrom($card) {
    try { return JSON.parse($card.attr('data-customer')); }
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
    $('#customersList').html(
        customers.length
            ? customers.map(cardTemplate).join('')
            : '<p class="py-16 text-center text-[10px] text-[#888]">No se encontraron clientes.</p>',
    );
}

function renderDetail(customer) {
    const available = subtract(
        String(customer.credit_limit ?? '0'),
        String(customer.balance ?? '0'),
    );

    $('#customerDetail').html(`
        <div class="w-full max-w-[330px] text-left">
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
        </div>`);
}

async function searchCustomers(search) {
    const endpoint = $('#customersRoot').data('customers-url');

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
    const $root = $('#customersRoot');
    if (!$root.length) return;

    $root
        .on('click keydown', '[data-customer-card]', function (event) {
            if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            $('[data-customer-card]').removeClass('border-[#087F98] bg-[#F8FCFD] ring-1 ring-[#087F98]/20');
            $(this).addClass('border-[#087F98] bg-[#F8FCFD] ring-1 ring-[#087F98]/20');
            const customer = customerFrom($(this));
            if (customer) renderDetail(customer);
        })
        .on('click', '[data-profile-filter]', function () {
            $('[data-profile-filter]').removeClass('border-[#087F98] bg-[#087F98] text-white');
            $(this).addClass('border-[#087F98] bg-[#087F98] text-white');
            const type = $(this).data('profile-filter');
            $('[data-customer-card]').each(function () {
                $(this).toggle(type === 'all' || $(this).data('type') === type);
            });
        })
        .on('input', '#customerSearch', function () {
            clearTimeout(debounceTimer);
            const query = this.value.trim();
            debounceTimer = setTimeout(() => searchCustomers(query), 350);
        });
}
