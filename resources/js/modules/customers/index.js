import { api, ApiError } from '@/core/api-client';
import { escapeHtml } from '@/core/dom';
import { money } from '@/core/money';
import { getSessionContext } from '@/core/session-context';

let debounceTimer;
const esc = (value) => escapeHtml(value, '—');
const fmt = (value) => `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

function customerFrom(card) {
    try { return JSON.parse(card.dataset.customer); }
    catch { return null; }
}

function cardTemplate(customer) {
    return `<article tabindex="0" data-customer-card
        data-customer="${esc(JSON.stringify(customer))}"
        class="grid cursor-pointer gap-4 rounded-[14px] border border-[#D6D6D6] bg-white px-5 py-5 transition hover:border-[#9ABFC8] hover:shadow-sm sm:grid-cols-[1.35fr_1fr_auto]">
        <div class="min-w-0">
            <h2 class="truncate text-[12px] font-bold text-[#202020]">${esc(customer.name)}</h2>
            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[8px] ${customer.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}">${customer.is_active ? 'Activo' : 'Inactivo'}</span>
            <p class="mt-3 text-[8px] text-[#696969]">${esc(customer.document_type_label)}: ${esc(customer.document_number)}</p>
        </div>
        <dl class="space-y-2 pt-1 text-[8px] text-[#777]">
            <div><dt class="inline font-semibold">Teléfono:</dt> <dd class="inline">${esc(customer.phone_number)}</dd></div>
            <div><dt class="inline font-semibold">Correo:</dt> <dd class="inline">${esc(customer.email)}</dd></div>
        </dl>
        <div class="sm:text-right">
            <p class="text-[8px] text-[#777]">Límite de crédito</p>
            <p class="mt-1 text-[15px] font-bold text-[#202020]">${fmt(customer.credit_limit)}</p>
        </div>
    </article>`;
}

function renderCustomers(customers) {
    const list = document.querySelector('#customersList');
    if (!list) return;
    list.innerHTML = customers.length
        ? customers.map(cardTemplate).join('')
        : '<p class="py-16 text-center text-sm text-[#777]">No se encontraron clientes.</p>';
}

function renderDetail(customer) {
    const detail = document.querySelector('#customerDetail');
    if (!detail) return;
    detail.innerHTML = `
        <div class="w-full max-w-xs text-left">
            <div class="border-b border-[#E8E8E8] pb-5">
                <p class="text-[17px] font-bold text-[#202020]">${esc(customer.name)}</p>
                <p class="mt-2 text-[9px] text-[#777]">${esc(customer.document_type_label)} · ${esc(customer.document_number)}</p>
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-4 text-[9px]">
                <div><dt class="text-[#888]">Teléfono</dt><dd class="mt-1 font-semibold">${esc(customer.phone_number)}</dd></div>
                <div><dt class="text-[#888]">Correo</dt><dd class="mt-1 break-words font-semibold">${esc(customer.email)}</dd></div>
                <div><dt class="text-[#888]">Límite de crédito</dt><dd class="mt-1 font-semibold">${fmt(customer.credit_limit)}</dd></div>
                <div><dt class="text-[#888]">Estado</dt><dd class="mt-1 font-semibold">${customer.is_active ? 'Activo' : 'Inactivo'}</dd></div>
            </dl>
            <p class="mt-5 text-[9px] leading-5 text-[#777]">${esc(customer.notes || 'Sin observaciones registradas')}</p>
        </div>`;
}

function errorMessage(error) {
    if (!(error instanceof ApiError)) return 'No fue posible consultar los clientes.';
    if (error.status === 403) return 'No tienes autorización para consultar clientes.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo cargar los clientes.';
    return error.message;
}

async function loadCustomers(search = '') {
    const root = document.querySelector('#customersRoot');
    root.setAttribute('aria-busy', 'true');
    try {
        const response = await api.get('/customers', {
            search: search.length >= 2 ? search : undefined,
            per_page: 25,
        }, { dispatchErrors: false });
        renderCustomers(Array.isArray(response?.data) ? response.data : []);
    } catch (error) {
        const list = document.querySelector('#customersList');
        if (list) list.innerHTML = `<p class="py-16 text-center text-sm text-red-700">${esc(errorMessage(error))}</p>`;
    } finally {
        root.setAttribute('aria-busy', 'false');
    }
}

export default async function init() {
    const root = document.querySelector('#customersRoot');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    const context = await getSessionContext();
    const createLink = root.querySelector('[data-create-customer]');
    if (createLink) {
        createLink.hidden = context.role === 'ROL-03' || !context.capabilities.includes('clientes.gestionar');
    }

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

    root.addEventListener('click', selectCustomer);
    root.addEventListener('keydown', selectCustomer);
    root.querySelector('#customerSearch')?.addEventListener('input', (event) => {
        clearTimeout(debounceTimer);
        const query = event.currentTarget.value.trim();
        if (query.length === 1) return;
        debounceTimer = setTimeout(() => loadCustomers(query), 350);
    });

    return loadCustomers();
}
