import { add, money, SCALE } from '../../core/money.js';

/** Solo este conflicto conocido revierte la factura dentro de la transacción de emisión. */
export function canRetryInvoice(error) {
    return error?.status === 409 && error.code === 'INSUFFICIENT_STOCK';
}

export function confirmedSale(payload, saleId, branchId) {
    const sale = payload?.data;
    if (sale?.id !== saleId || sale.branch_id !== branchId) throw new TypeError('La venta consultada no corresponde al ticket o la sucursal actual.');
    if (sale.status === 'facturada') throw new TypeError(`La venta #${saleId} ya fue facturada. No se repitió la emisión.`);
    if (sale.status !== 'confirmada' || !Array.isArray(sale.items) || sale.items.length === 0) {
        throw new TypeError(`La venta #${saleId} no está confirmada con líneas consultables. Revisa su estado antes de continuar.`);
    }
    return sale;
}

export function invoicePayload(sale, method, cashSessionId = null) {
    let amount = '0.00';
    for (const line of sale.items) {
        if (line.sale_id !== sale.id || !Number.isInteger(line.id)
            || !/^\d+(?:\.\d{1,2})?$/.test(line.line_total) || !/^\d+(?:\.\d{1,2})?$/.test(line.tax_amount)) {
            throw new TypeError('Las líneas no incluyen sus importes fiscales confirmados. No se emitió la factura.');
        }
        amount = add(amount, line.line_total, SCALE.MONEY);
        amount = add(amount, line.tax_amount, SCALE.MONEY);
    }
    const payload = { sale_ids: [sale.id], payment_type: 'contado', payments: [{ method, amount: money(amount), reference: null }] };
    if (method === 'efectivo') payload.cash_session_id = cashSessionId;
    return payload;
}
