import { api, ApiError } from '@/core/api-client';
import { escapeHtml } from '@/core/dom';
import { withLoading, setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';
import { add, subtract, multiply, money, quantity, SCALE } from '@/core/money';

let products = [], cart = new Map();
let submitting = false;
const esc = value => escapeHtml(value);
const fmt = value => `C$ ${money(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

function renderProducts(list = products) {
    const container = document.querySelector('#posProducts');
    if (!container) return;

    container.innerHTML = list.map(p => `
        <button type="button" data-product="${p.id}"
            class="min-h-36 rounded-xl border border-neutral-300 bg-white p-3 text-left transition hover:border-cyan-800/50 hover:shadow-sm">
            <div class="grid h-12 w-12 place-items-center rounded-full bg-[#F3F3F3] text-xl">📦</div>
            <p class="mt-3 truncate text-[10px] font-semibold text-[#282828]">${esc(p.name)}</p>
            <p class="mt-1 text-[8px] text-[#888]">${esc(p.sku)}</p>
            <p class="mt-2 text-[11px] font-bold text-[#222]">${fmt(p.sale_price)}</p>
        </button>`).join('');
}

function totals() {
    let subtotal = '0.00', taxable = '0.00';
    cart.forEach(({ product, qty }) => {
        const line = multiply(product.sale_price, qty, SCALE.MONEY);
        subtotal = add(subtotal, line);
        if (product.is_taxable) taxable = add(taxable, line);
    });
    const taxRate = document.querySelector('#posRoot')?.dataset.taxRate ?? '0';
    const tax = multiply(taxable, taxRate, SCALE.MONEY);
    return { subtotal: money(subtotal), tax, total: add(subtotal, tax) };
}

function renderCart() {
    const rows = [...cart.values()];
    const empty = document.querySelector('#posEmpty');
    const cartContainer = document.querySelector('#posCart');
    if (!cartContainer) return;

    if (empty) empty.hidden = rows.length > 0;
    cartContainer.querySelectorAll('[data-cart-row]').forEach((row) => row.remove());
    rows.forEach(({ product: p, qty }) => cartContainer.insertAdjacentHTML('afterbegin', `
        <div data-cart-row="${p.id}" class="flex items-center gap-2 rounded-lg border border-[#E4E4E4] p-2">
            <div class="min-w-0 flex-1"><p class="truncate text-[9px] font-semibold">${esc(p.name)}</p>
            <p class="text-[8px] text-[#777]">${fmt(p.sale_price)}</p></div>
            <button type="button" data-qty="-1" class="h-6 w-6 rounded border">−</button>
            <span class="w-8 text-center text-[9px]">${qty}</span>
            <button type="button" data-qty="1" class="h-6 w-6 rounded border">+</button>
            <button type="button" data-remove class="ml-1 text-[12px] text-red-500">×</button>
        </div>`));
    const t = totals();
    const values = {
        posSubtotal: fmt(t.subtotal),
        posTax: fmt(t.tax),
        posTotal: fmt(t.total),
        posItemCount: `${rows.length} artículos`,
    };
    Object.entries(values).forEach(([id, value]) => {
        const element = document.querySelector(`#${id}`);
        if (element) element.textContent = value;
    });
}

function payload() {
    const t = totals();
    return {
        branch_id: document.querySelector('#posForm [name="branch_id"]')?.value,
        customer_id: document.querySelector('#posForm [name="customer_id"]')?.value,
        payment_type: 'contado',
        items: [...cart.values()].map(({ product, qty }) => ({ product_id: product.id, quantity: quantity(qty) })),
        payments: [{ payment_method: document.querySelector('#paymentMethod')?.value, amount: t.total }],
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
    if (submitting) return;

    const endpoint = document.querySelector('#posRoot')?.dataset.checkoutUrl;
    if (!endpoint || !cart.size) return notify({ type: 'warning', message: !cart.size ? 'Agregue productos al ticket.' : 'Endpoint POS no configurado.' });
    submitting = true;
    setButtonLoading(button, true, { label: 'Procesando...' });
    try {
        const response = await api.post(endpoint, payload());
        cart.clear(); renderCart();
        notify({ type: 'success', message: `Venta ${response?.data?.folio ?? ''} registrada correctamente.` });
    } catch (error) {
        if (error instanceof ApiError && ![401, 403].includes(error.status))
            notify({ type: error.status === 422 ? 'warning' : 'error', message: error.message });
    } finally {
        setButtonLoading(button, false);
        submitting = false;
    }
}

export default function init() {
    const root = document.querySelector('#posRoot');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    loadProducts().catch(error => console.error('[Gintly POS]', error));

    root.addEventListener('click', (event) => {
        const productButton = event.target.closest('[data-product]');
        const quantityButton = event.target.closest('[data-cart-row] [data-qty]');
        const removeButton = event.target.closest('[data-remove]');
        const paymentButton = event.target.closest('[data-payment]');

        if (productButton && root.contains(productButton)) {
            const product = products.find(item => String(item.id) === productButton.dataset.product);
            if (!product) return;

            const row = cart.get(product.id);
            cart.set(product.id, {
                product,
                qty: row ? add(row.qty, '1', SCALE.QUANTITY) : '1.000',
            });
            renderCart();
            return;
        }

        if (quantityButton && root.contains(quantityButton)) {
            const cartRow = quantityButton.closest('[data-cart-row]');
            const id = Number(cartRow?.dataset.cartRow);
            const row = cart.get(id);
            if (!row) return;

            const qty = Number(quantityButton.dataset.qty) > 0
                ? add(row.qty, '1', SCALE.QUANTITY)
                : subtract(row.qty, '1', SCALE.QUANTITY);
            if (money(qty) === '0.00') cart.delete(id);
            else cart.set(id, { ...row, qty });
            renderCart();
            return;
        }

        if (removeButton && root.contains(removeButton)) {
            const id = Number(removeButton.closest('[data-cart-row]')?.dataset.cartRow);
            cart.delete(id);
            renderCart();
            return;
        }

        if (paymentButton && root.contains(paymentButton)) {
            const paymentMethod = root.querySelector('#paymentMethod');
            if (paymentMethod) paymentMethod.value = paymentButton.dataset.payment;

            root.querySelectorAll('[data-payment]').forEach((button) => {
                button.className = 'h-10 rounded-lg border border-[#DDD] bg-[#F8F8F8] text-[9px] text-[#555]';
            });
            paymentButton.className = 'h-10 rounded-lg border border-[#72C98D] bg-[#DDF6E5] text-[9px] font-medium text-[#258446]';
        }
    });

    root.addEventListener('input', (event) => {
        if (event.target.matches('#posSearch')) {
            const query = event.target.value.trim().toLowerCase();
            renderProducts(products.filter(product =>
                `${product.name} ${product.sku}`.toLowerCase().includes(query),
            ));
        }
    });

    root.querySelector('#posForm')?.addEventListener('submit', (event) => {
        event.preventDefault();
        void checkout(event.currentTarget.querySelector('[data-submit]'));
    });
}
