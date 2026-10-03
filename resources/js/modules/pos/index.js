import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { escapeHtml } from '@/core/dom';
import { setButtonLoading } from '@/core/loading';
import { add, compare, money, multiply, quantity, SCALE } from '@/core/money';
import { notify } from '@/core/notifications';
import { getSessionContext } from '@/core/session-context';

const esc = (value) => escapeHtml(value);
const fmt = (value) => `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

class PointOfSale {
    constructor(root) {
        this.root = root;
        this.products = [];
        this.cart = new Map();
        this.context = null;
        this.cashSession = null;
        this.submitting = false;
    }

    async init() {
        this.bind();
        try {
            this.context = await getSessionContext();
            this.assertAccess();
            await Promise.all([this.loadProducts(), this.loadCustomers(), this.loadCashContext()]);
            this.renderCart();
        } catch (error) {
            this.showError(error);
        } finally {
            this.root.setAttribute('aria-busy', 'false');
        }
    }

    assertAccess() {
        const allowed = this.context.role === 'ROL-03'
            && this.context.profiles.includes('facturador')
            && ['ventas.crear', 'facturas.crear', 'catalogo.ver', 'clientes.ver']
                .every((capability) => this.context.capabilities.includes(capability))
            && Number.isInteger(this.context.branch.id);

        if (!allowed) throw new Error('El punto de venta no está disponible para tu perfil operativo.');
    }

    bind() {
        this.root.addEventListener('click', (event) => this.handleClick(event));
        this.root.querySelector('#posSearch')?.addEventListener('input', (event) => {
            const query = event.currentTarget.value.trim().toLocaleLowerCase('es');
            this.renderProducts(this.products.filter((product) =>
                `${product.name} ${product.sku}`.toLocaleLowerCase('es').includes(query)));
        });
        this.root.querySelector('#posForm')?.addEventListener('submit', (event) => {
            event.preventDefault();
            void this.checkout(event.currentTarget.querySelector('[data-submit]'));
        });
    }

    async loadProducts() {
        const response = await api.get('/products', {
            is_active: true,
            per_page: 100,
        }, { dispatchErrors: false });
        if (!Array.isArray(response?.data)) throw new TypeError('El servidor no devolvió productos válidos.');
        this.products = response.data;
        this.renderProducts();
    }

    async loadCustomers() {
        const response = await api.get('/customers', {
            include_generic: true,
            is_active: true,
            per_page: 100,
        }, { dispatchErrors: false });
        if (!Array.isArray(response?.data)) throw new TypeError('El servidor no devolvió clientes válidos.');
        const customers = response.data;
        const select = this.root.querySelector('[name="customer_id"]');
        const prompt = document.createElement('option');
        prompt.value = '';
        prompt.textContent = customers.length ? 'Selecciona un cliente' : 'No hay clientes disponibles';
        const options = customers.map((customer) => {
            const option = document.createElement('option');
            option.value = String(customer.id);
            option.textContent = customer.name;
            return option;
        });
        select.replaceChildren(prompt, ...options);
        select.disabled = customers.length === 0;
    }

    async loadCashContext() {
        const canUseCash = this.context.profiles.includes('cajero')
            && this.context.capabilities.includes('caja.movimiento.crear');
        const cashButton = this.root.querySelector('[data-payment="efectivo"]');
        if (!canUseCash) {
            cashButton?.remove();
            this.setPaymentMethod('transferencia');
            return;
        }

        const response = await api.get('/cash-sessions/current', {}, { dispatchErrors: false });
        if (!response || typeof response !== 'object' || !('data' in response)
            || (response.data !== null && !Number.isInteger(response.data?.id))) {
            throw new TypeError('El servidor no devolvió un estado de caja válido.');
        }
        this.cashSession = response.data;
        if (!this.cashSession) {
            cashButton?.setAttribute('disabled', '');
            cashButton?.setAttribute('title', 'Debes abrir tu sesión de caja para cobrar en efectivo.');
            this.setPaymentMethod('transferencia');
        }
    }

    renderProducts(list = this.products) {
        const container = this.root.querySelector('#posProducts');
        if (!container) return;
        if (!list.length) {
            container.innerHTML = '<p class="col-span-full rounded-xl border border-dashed border-neutral-300 p-6 text-center text-sm text-neutral-600">No hay productos disponibles.</p>';
            return;
        }
        container.innerHTML = list.map((product) => `
            <button type="button" data-product="${product.id}"
                class="min-h-36 rounded-xl border border-neutral-300 bg-white p-3 text-left transition hover:border-cyan-800/50 hover:shadow-sm">
                <span aria-hidden="true" class="grid h-12 w-12 place-items-center rounded-full bg-[#F3F3F3] text-xl">📦</span>
                <span class="mt-3 block truncate text-[10px] font-semibold text-[#282828]">${esc(product.name)}</span>
                <span class="mt-1 block text-[8px] text-[#888]">${esc(product.sku)}</span>
                <span class="mt-2 block text-[11px] font-bold text-[#222]">${fmt(product.sale_price)}</span>
            </button>`).join('');
    }

    subtotal() {
        let total = '0.00';
        this.cart.forEach(({ product, qty }) => {
            total = add(total, multiply(product.sale_price, qty, SCALE.MONEY), SCALE.MONEY);
        });
        return money(total);
    }

    renderCart() {
        const rows = [...this.cart.values()];
        const container = this.root.querySelector('#posCart');
        const empty = this.root.querySelector('#posEmpty');
        if (!container) return;
        if (empty) empty.hidden = rows.length > 0;
        container.querySelectorAll('[data-cart-row]').forEach((row) => row.remove());
        rows.forEach(({ product, qty }) => container.insertAdjacentHTML('afterbegin', `
            <div data-cart-row="${product.id}" class="flex items-center gap-2 rounded-lg border border-[#E4E4E4] p-2">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[9px] font-semibold">${esc(product.name)}</p>
                    <p class="text-[8px] text-[#777]">${fmt(product.sale_price)}</p>
                </div>
                <button type="button" data-qty="-1" aria-label="Reducir cantidad de ${esc(product.name)}" class="min-h-11 min-w-11 rounded border">−</button>
                <span class="w-12 text-center text-[9px]">${esc(qty)}</span>
                <button type="button" data-qty="1" aria-label="Aumentar cantidad de ${esc(product.name)}" class="min-h-11 min-w-11 rounded border">+</button>
                <button type="button" data-remove aria-label="Quitar ${esc(product.name)}" class="min-h-11 min-w-11 rounded text-[12px] text-red-600">×</button>
            </div>`));
        this.root.querySelector('#posSubtotal').textContent = fmt(this.subtotal());
        this.root.querySelector('#posItemCount').textContent = `${rows.length} ${rows.length === 1 ? 'artículo' : 'artículos'}`;
    }

    handleClick(event) {
        const productButton = event.target.closest('[data-product]');
        const quantityButton = event.target.closest('[data-cart-row] [data-qty]');
        const removeButton = event.target.closest('[data-remove]');
        const paymentButton = event.target.closest('[data-payment]');

        if (productButton && this.root.contains(productButton)) {
            const product = this.products.find((item) => String(item.id) === productButton.dataset.product);
            if (!product) return;
            const existing = this.cart.get(product.id);
            this.cart.set(product.id, { product, qty: existing ? add(existing.qty, '1', SCALE.QUANTITY) : '1.000' });
            this.renderCart();
        } else if (quantityButton && this.root.contains(quantityButton)) {
            const id = Number(quantityButton.closest('[data-cart-row]')?.dataset.cartRow);
            const row = this.cart.get(id);
            if (!row) return;
            const delta = quantityButton.dataset.qty;
            const next = delta === '1' ? add(row.qty, '1', SCALE.QUANTITY) : add(row.qty, '-1', SCALE.QUANTITY);
            if (compare(next, '0') <= 0) this.cart.delete(id);
            else this.cart.set(id, { ...row, qty: next });
            this.renderCart();
        } else if (removeButton && this.root.contains(removeButton)) {
            this.cart.delete(Number(removeButton.closest('[data-cart-row]')?.dataset.cartRow));
            this.renderCart();
        } else if (paymentButton && this.root.contains(paymentButton) && !paymentButton.disabled) {
            this.setPaymentMethod(paymentButton.dataset.payment);
        }
    }

    setPaymentMethod(method) {
        const hidden = this.root.querySelector('#paymentMethod');
        if (hidden) hidden.value = method;
        this.root.querySelectorAll('[data-payment]').forEach((button) => {
            const selected = button.dataset.payment === method;
            button.className = `h-11 rounded-lg border text-[9px] font-medium transition ${selected
                ? 'border-[#72C98D] bg-[#DDF6E5] text-[#258446]'
                : 'border-[#DDD] bg-[#F8F8F8] text-[#555]'}`;
            button.setAttribute('aria-pressed', String(selected));
        });
    }

    async mutate(path, payload) {
        const options = { dispatchErrors: false };
        try { return await api.post(path, payload, options); }
        catch (error) {
            if (!(error instanceof ApiError) || error.status !== 419) throw error;
            await initializeCsrf({ dispatchErrors: false });
            return api.post(path, payload, options);
        }
    }

    async checkout(button) {
        if (this.submitting) return;
        const customerId = this.root.querySelector('[name="customer_id"]')?.value;
        const method = this.root.querySelector('#paymentMethod')?.value;
        if (!customerId) return this.announce('Selecciona un cliente antes de continuar.');
        if (!this.cart.size) return this.announce('Agrega al menos un producto a la venta.');
        if (method === 'efectivo' && !this.cashSession) return this.announce('Abre tu sesión de caja antes de cobrar en efectivo.');

        this.submitting = true;
        setButtonLoading(button, true, { label: 'Procesando…' });
        let saleId = null;
        try {
            const saleResponse = await this.mutate('/sales', {
                branch_id: this.context.branch.id,
                customer_id: Number(customerId),
                notes: null,
            });
            saleId = saleResponse?.data?.id;
            if (!saleId) throw new Error('El servidor no devolvió el identificador de la venta.');

            const lines = [];
            for (const { product, qty } of this.cart.values()) {
                const lineResponse = await this.mutate(`/sales/${saleId}/items`, {
                    product_id: product.id,
                    quantity: quantity(qty),
                });
                lines.push(lineResponse?.data);
            }
            const confirmed = await this.mutate(`/sales/${saleId}/confirm`, {});

            let invoiceAmount = '0.00';
            const confirmedLines = Array.isArray(confirmed?.data?.items) ? confirmed.data.items : lines;
            confirmedLines.forEach((line) => {
                invoiceAmount = add(invoiceAmount, line?.line_total ?? '0.00', SCALE.MONEY);
                invoiceAmount = add(invoiceAmount, line?.tax_amount ?? '0.00', SCALE.MONEY);
            });
            const invoicePayload = {
                sale_ids: [saleId],
                payment_type: 'contado',
                payments: [{ method, amount: money(invoiceAmount), reference: null }],
            };
            if (method === 'efectivo') invoicePayload.cash_session_id = this.cashSession.id;
            const invoice = await this.mutate('/invoices', invoicePayload);

            this.cart.clear();
            this.renderCart();
            this.announce(`Factura ${invoice?.data?.folio ?? `#${invoice?.data?.id}`} emitida correctamente.`);
            notify({ type: 'success', message: 'Venta confirmada y factura emitida.' });
        } catch (error) {
            const suffix = saleId ? ` La venta #${saleId} quedó registrada para revisión; no se reintentó automáticamente.` : '';
            this.announce(`${this.errorMessage(error)}${suffix}`);
        } finally {
            setButtonLoading(button, false);
            this.submitting = false;
        }
    }

    errorMessage(error) {
        if (!(error instanceof ApiError)) return error?.message || 'No fue posible completar la operación.';
        if (error.status === 403) return 'No tienes autorización para completar esta operación.';
        if ([409, 422].includes(error.status)) return error.message;
        if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
        if (error.status === 0) return 'No fue posible conectar con el servidor.';
        if (error.status >= 500) return 'El servidor no pudo completar la operación.';
        return error.message;
    }

    announce(message) {
        const live = this.root.querySelector('[data-pos-live]');
        if (live) live.textContent = message;
        notify({ type: 'warning', message });
    }

    showError(error) {
        this.announce(this.errorMessage(error));
        this.root.querySelectorAll('form input, form select, form button').forEach((control) => { control.disabled = true; });
    }
}

export default function init() {
    const root = document.querySelector('#posRoot');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new PointOfSale(root).init();
}
