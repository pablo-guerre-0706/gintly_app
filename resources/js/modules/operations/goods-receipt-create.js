import { ApiError } from '@/core/api-client';
import { compare, cost, money, quantity } from '@/core/money';
import { getSessionContext } from '@/core/session-context';
import { DECIMAL_2, DECIMAL_3, DECIMAL_4, clearErrors, errorMessage, makeField, options, read, records, requireBodeguero, setNotice, showErrors, write } from './write-support';

class ReceiptPage {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-operation-form]');
        this.lines = root.querySelector('[data-operation-lines]');
        this.branchId = null;
        this.loadController = null;
        this.orderController = null;
        this.submitting = false;
        this.order = null;
    }

    init() {
        this.root.querySelector('[data-operation-retry]').addEventListener('click', () => this.load());
        this.form.elements.namedItem('purchase_order_id').addEventListener('change', () => this.loadOrder());
        this.lines.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-line]');
            if (!button || this.lines.children.length === 1) return;
            button.closest('[data-line]').remove();
            this.renumber();
        });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        return this.load();
    }

    async load() {
        this.loadController?.abort();
        const controller = new AbortController();
        this.loadController = controller;
        this.root.setAttribute('aria-busy', 'true');
        this.root.querySelector('[data-operation-loading]').hidden = false;
        this.root.querySelector('[data-operation-fatal]').hidden = true;
        this.form.hidden = true;
        try {
            const context = await getSessionContext();
            requireBodeguero(context, 'compras.recibir');
            this.branchId = context.branch.id;
            this.root.querySelector('[data-operation-branch]').textContent = `Sucursal #${this.branchId}`;
            const [issued, partial, warehousePayload] = await Promise.all([
                read('/purchase-orders', { status: 'emitida', per_page: 100 }, controller.signal),
                read('/purchase-orders', { status: 'parcial', per_page: 100 }, controller.signal),
                read('/warehouses', { is_active: true, per_page: 100 }, controller.signal),
            ]);
            if (controller.signal.aborted) return;
            const orders = [...records(issued), ...records(partial)].filter((order) => order.branch_id === this.branchId);
            const warehouses = records(warehousePayload).filter((warehouse) => warehouse.branch_id === this.branchId && warehouse.is_active === true);
            if (!orders.length) throw new TypeError('No hay órdenes emitidas o parcialmente recibidas en tu sucursal. Administración debe emitir un borrador antes de la recepción.');
            if (!warehouses.length) throw new TypeError('No existe una bodega activa autorizada para recibir en tu sucursal.');
            options(this.form.elements.namedItem('purchase_order_id'), orders, (order) => `${order.code} · ${order.status_label}`, 'Sin órdenes recepcionables');
            options(this.form.elements.namedItem('warehouse_id'), warehouses, (warehouse) => warehouse.name, 'Sin bodegas');
            this.lines.replaceChildren();
            this.form.hidden = false;
        } catch (error) {
            if (controller.signal.aborted) return;
            this.root.querySelector('[data-operation-fatal-message]').textContent = errorMessage(error);
            this.root.querySelector('[data-operation-fatal]').hidden = false;
        } finally {
            if (this.loadController === controller) {
                this.root.querySelector('[data-operation-loading]').hidden = true;
                this.root.setAttribute('aria-busy', 'false');
            }
        }
    }

    async loadOrder() {
        this.orderController?.abort();
        this.order = null;
        this.lines.replaceChildren();
        const id = Number(this.form.elements.namedItem('purchase_order_id').value);
        if (!id) return;
        const controller = new AbortController();
        this.orderController = controller;
        this.root.setAttribute('aria-busy', 'true');
        try {
            const payload = await read(`/purchase-orders/${id}`, {}, controller.signal);
            if (controller.signal.aborted) return;
            const order = payload?.data;
            if (!order || order.branch_id !== this.branchId || !['emitida', 'parcial'].includes(order.status) || !Array.isArray(order.items)) {
                throw new TypeError('La orden no pertenece a tu sucursal o ya no admite recepción.');
            }
            this.order = order;
            order.items.filter((item) => compare(String(item.pending_quantity), '0') > 0).forEach((item) => this.addLine(item));
            if (!this.lines.children.length) throw new TypeError('La orden seleccionada no tiene líneas pendientes de recepción.');
        } catch (error) {
            if (controller.signal.aborted) return;
            setNotice(this.root, errorMessage(error), true);
        } finally {
            if (this.orderController === controller) this.root.setAttribute('aria-busy', 'false');
        }
    }

    addLine(item) {
        const index = this.lines.children.length;
        const row = document.createElement('div');
        const title = document.createElement('p');
        const remove = document.createElement('button');
        row.dataset.line = '';
        row.dataset.orderItemId = String(item.id);
        row.className = 'grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_9rem_9rem_auto] sm:items-start';
        title.className = 'text-sm font-semibold';
        title.textContent = `${item.product?.name ?? `Producto #${item.product_id}`} · pendiente ${quantity(String(item.pending_quantity))}`;
        row.append(title,
            makeField({ name: `lines.${index}.received_quantity`, label: 'Cantidad recibida', value: quantity(String(item.pending_quantity)) }),
            makeField({ name: `lines.${index}.invoiced_unit_cost`, label: 'Costo facturado', value: cost(String(item.agreed_unit_cost)) }),
        );
        remove.type = 'button';
        remove.dataset.removeLine = '';
        remove.className = 'min-h-11 rounded-xl border border-slate-300 px-3 text-sm font-semibold';
        remove.textContent = 'Omitir línea';
        row.append(remove);
        this.lines.append(row);
    }

    renumber() {
        this.lines.querySelectorAll('[data-line]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((control) => { control.name = control.name.replace(/lines\.\d+\./, `lines.${index}.`); });
            row.querySelectorAll('[data-field-error]').forEach((node) => { node.dataset.fieldError = node.dataset.fieldError.replace(/lines\.\d+\./, `lines.${index}.`); });
        });
    }

    payload() {
        const errors = {};
        const orderId = Number(this.form.elements.namedItem('purchase_order_id').value);
        const warehouseId = Number(this.form.elements.namedItem('warehouse_id').value);
        if (!orderId || this.order?.id !== orderId) errors.purchase_order_id = ['Selecciona una orden vigente y espera a que carguen sus líneas.'];
        if (!warehouseId) errors.warehouse_id = ['Selecciona una bodega receptora.'];
        const invoiceTotal = this.form.elements.namedItem('supplier_invoice_total').value.trim();
        const tolerance = this.form.elements.namedItem('tolerance').value.trim();
        if (invoiceTotal && !DECIMAL_2.test(invoiceTotal)) errors.supplier_invoice_total = ['Usa un total no negativo con máximo dos decimales.'];
        if (tolerance && !DECIMAL_4.test(tolerance)) errors.tolerance = ['Usa una tolerancia no negativa con máximo cuatro decimales.'];
        const rows = [...this.lines.querySelectorAll('[data-line]')];
        if (!rows.length) errors.lines = ['La recepción necesita al menos una línea.'];
        const lines = rows.map((row, index) => {
            const [qtyInput, costInput] = row.querySelectorAll('input');
            const qty = qtyInput.value.trim();
            const unitCost = costInput.value.trim();
            const orderItem = this.order?.items.find((item) => item.id === Number(row.dataset.orderItemId));
            if (!orderItem) errors[`lines.${index}.received_quantity`] = ['La línea ya no pertenece a la orden.'];
            if (!DECIMAL_3.test(qty) || compare(qty, '0') <= 0) errors[`lines.${index}.received_quantity`] = ['Indica una cantidad positiva con máximo tres decimales.'];
            if (orderItem && DECIMAL_3.test(qty) && compare(qty, String(orderItem.pending_quantity)) > 0) errors[`lines.${index}.received_quantity`] = ['La cantidad supera lo pendiente de la orden.'];
            if (!DECIMAL_4.test(unitCost)) errors[`lines.${index}.invoiced_unit_cost`] = ['Indica un costo no negativo con máximo cuatro decimales.'];
            return {
                purchase_order_item_id: Number(row.dataset.orderItemId),
                received_quantity: DECIMAL_3.test(qty) ? quantity(qty) : qty,
                invoiced_unit_cost: DECIMAL_4.test(unitCost) ? cost(unitCost) : unitCost,
            };
        });
        if (Object.keys(errors).length) { showErrors(this.form, errors); return null; }
        const payload = {
            purchase_order_id: orderId,
            warehouse_id: warehouseId,
            supplier_invoice_number: this.form.elements.namedItem('supplier_invoice_number').value.trim() || null,
            lines,
        };
        if (invoiceTotal) payload.supplier_invoice_total = money(invoiceTotal);
        if (tolerance) payload.tolerance = cost(tolerance);
        return payload;
    }

    async submit() {
        if (this.submitting) return;
        clearErrors(this.form);
        const payload = this.payload();
        if (!payload) return;
        this.submitting = true;
        const button = this.root.querySelector('[data-operation-submit]');
        button.disabled = true;
        this.form.setAttribute('aria-busy', 'true');
        try {
            const response = await write('/goods-receipts', payload);
            if (!response?.data?.id) throw new TypeError('El servidor no confirmó la recepción. Comprueba su estado antes de reintentar.');
            setNotice(this.root, `Recepción #${response.data.id} registrada. Estado de conciliación: ${response.data.match_status_label}.`);
            this.form.hidden = true;
        } catch (error) {
            if (error instanceof ApiError && error.is(409, 'PURCHASE_MATCH') && error.payload?.data?.id) {
                const receipt = error.payload.data;
                setNotice(this.root, `Recepción #${receipt.id} ya registrada con discrepancia. La cuenta por pagar permanece ${receipt.account_payable?.status_label ?? 'en revisión'}; no repitas el envío.`, true);
                this.form.hidden = true;
            } else {
                setNotice(this.root, errorMessage(error), true);
                if (error instanceof ApiError && error.status === 422) showErrors(this.form, error.errors);
            }
        } finally {
            this.submitting = false;
            button.disabled = false;
            this.form.setAttribute('aria-busy', 'false');
        }
    }
}

export default function init() {
    const root = document.querySelector('[data-goods-receipt-create]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    return new ReceiptPage(root).init();
}
