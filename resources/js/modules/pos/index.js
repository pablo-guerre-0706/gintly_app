import $ from 'jquery';
import { api, ApiError } from '@/core/api-client';
import { withLoading, setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { add, subtract, multiply, money, quantity, SCALE } from '@/core/money';

let products = [], cart = new Map();
const esc = value => $('<div>').text(value ?? '').html();
const fmt = value => C$ ${money(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',')};

function renderProducts(list = products) {
    $('#posProducts').html(list.map(p => `
        <button type="button" data-product="${p.id}"
            class="min-h-[138px] rounded-xl border border-[#DDD] bg-white p-3 text-left transition hover:border-[#07839B]/50 hover:shadow-sm">
            <div class="grid h-12 w-12 place-items-center rounded-full bg-[#F3F3F3] text-xl">📦</div>
            <p class="mt-3 truncate text-[10px] font-semibold text-[#282828]">${esc(p.name)}</p>
            <p class="mt-1 text-[8px] text-[#888]">${esc(p.sku)}</p>
            <p class="mt-2 text-[11px] font-bold text-[#222]">${fmt(p.sale_price)}</p>
        </button>`).join(''));
}

function totals() {
    let subtotal = '0.00', taxable = '0.00';
    cart.forEach(({ product, qty }) => {
        const line = multiply(product.sale_price, qty, SCALE.MONEY);
        subtotal = add(subtotal, line);
        if (product.is_taxable) taxable = add(taxable, line);
    });
    const tax = multiply(taxable, $('#posRoot').data('tax-rate').toString(), SCALE.MONEY);
    return { subtotal: money(subtotal), tax, total: add(subtotal, tax) };
}

function renderCart() {
    const rows = [...cart.values()];
    $('#posEmpty').toggle(!rows.length);
    $('#posCart [data-cart-row]').remove();
    rows.forEach(({ product: p, qty }) => $('#posCart').prepend(`
        <div data-cart-row="${p.id}" class="flex items-center gap-2 rounded-lg border border-[#E4E4E4] p-2">
            <div class="min-w-0 flex-1"><p class="truncate text-[9px] font-semibold">${esc(p.name)}</p>
            <p class="text-[8px] text-[#777]">${fmt(p.sale_price)}</p></div>
            <button type="button" data-qty="-1" class="h-6 w-6 rounded border">−</button>
            <span class="w-8 text-center text-[9px]">${qty}</span>
            <button type="button" data-qty="1" class="h-6 w-6 rounded border">+</button>
            <button type="button" data-remove class="ml-1 text-[12px] text-red-500">×</button>
        </div>`));
    const t = totals();
    $('#posSubtotal').text(fmt(t.subtotal)); $('#posTax').text(fmt(t.tax)); $('#posTotal').text(fmt(t.total));
    $('#posItemCount').text(${rows.length} artículos);
}

function payload() {
    const t = totals(), root = $('#posRoot');
    return {
        branch_id: $('#posForm [name="branch_id"]').val(),
        customer_id: $('#posForm [name="customer_id"]').val(),
        payment_type: 'contado',
        items: [...cart.values()].map(({ product, qty }) => ({ product_id: product.id, quantity: quantity(qty) })),
        payments: [{ payment_method: $('#paymentMethod').val(), amount: t.total }],
    };
}

async function loadProducts() {
    const response = await withLoading(
        () => api.get('/products', { available: true, is_active: true, per_page: 100 }),
        { message: 'Cargando catálogo...' },
    );
    products = response?.data ?? []; renderProducts();
}

async function checkout(button) {
    const endpoint = $('#posRoot').data('checkout-url');
    if (!endpoint || !cart.size) return notify({ type: 'warning', message: !cart.size ? 'Agregue productos al ticket.' : 'Endpoint POS no configurado.' });
    setButtonLoading(button, true, { label: 'Procesando...' });
    try {
        const response = await api.post(endpoint, payload());
        cart.clear(); renderCart();
        notify({ type: 'success', message: `Venta ${response?.data?.folio ?? ''} registrada correctamente.` });
    } catch (error) {
        if (error instanceof ApiError && ![401, 403].includes(error.status))
            notify({ type: error.status === 422 ? 'warning' : 'error', message: error.message });
    } finally { setButtonLoading(button, false); }
}

export default function init() {
    if (!$('#posRoot').length) return;
    loadProducts().catch(error => console.error('[Gintly POS]', error));

    $(document).on('click', '[data-product]', function () {
        const p = products.find(x => String(x.id) === String($(this).data('product')));
        if (!p) return;
        const row = cart.get(p.id); cart.set(p.id, { product: p, qty: row ? add(row.qty, '1', SCALE.QUANTITY) : '1.000' }); renderCart();
    }).on('click', '[data-cart-row] [data-qty]', function () {
        const id = Number($(this).closest('[data-cart-row]').data('cart-row')), row = cart.get(id);
        const qty = $(this).data('qty') > 0 ? add(row.qty, '1', SCALE.QUANTITY) : subtract(row.qty, '1', SCALE.QUANTITY);
        money(qty) === '0.00' ? cart.delete(id) : cart.set(id, { ...row, qty }); renderCart();
    }).on('click', '[data-remove]', function () {
        cart.delete(Number($(this).closest('[data-cart-row]').data('cart-row'))); renderCart();
    }).on('click', '[data-payment]', function () {
        $('#paymentMethod').val($(this).data('payment'));
        $('[data-payment]').attr('class', 'h-10 rounded-lg border border-[#DDD] bg-[#F8F8F8] text-[9px] text-[#555]');
        $(this).attr('class', 'h-10 rounded-lg border border-[#72C98D] bg-[#DDF6E5] text-[9px] font-medium text-[#258446]');
    }).on('input', '#posSearch', function () {
        const q = this.value.trim().toLowerCase(); renderProducts(products.filter(p => `${p.name} ${p.sku}`.toLowerCase().includes(q)));
    }).on('submit', '#posForm', function (e) {
        e.preventDefault(); checkout($(this).find('[data-submit]').get(0));
    });
}
