import { money } from '@/core/money';

const text = (value) => value === null || value === undefined || value === '' ? '—' : String(value);
const amount = (value) => value === null || value === undefined ? '—' : money(String(value));
const date = (value) => {
    if (!value) return '—';
    const parsed = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value);
    if (Number.isNaN(parsed.getTime())) throw new TypeError('El recurso devolvió una fecha inválida.');
    return new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium' }).format(parsed);
};
const status = (record, labelKey = 'status_label', key = 'status') => text(record[labelKey] ?? record[key]);

const define = (endpoint, columns, map, { search = false, searchLabel = '', searchPlaceholder = '' } = {}) => Object.freeze({
    endpoint, columns: Object.freeze(columns), map, search, searchLabel, searchPlaceholder,
});

// Cada columna corresponde a una propiedad explícita del Resource respectivo.
// Ninguna lista permite elegir una sucursal: el Backend acota el índice a user.branch_id.
export const OPERATIVE_RESOURCES = Object.freeze({
    invoices: define('/invoices', ['Folio', 'Cliente', 'Estado', 'Pago', 'Total', 'Emitida'], (record) => [
        text(record.folio), text(record.customer?.name), status(record),
        text(record.payment_status), amount(record.total), date(record.issued_at),
    ]),
    sales: define('/sales', ['Código', 'Cliente', 'Estado', 'Subtotal', 'Apertura'], (record) => [
        text(record.code), text(record.customer?.name), status(record), amount(record.subtotal), date(record.opened_at),
    ]),
    transfers: define('/stock-transfers', ['Código', 'Origen', 'Destino', 'Estado', 'Fecha'], (record) => [
        text(record.code), text(record.from_warehouse?.name), text(record.to_warehouse?.name),
        status(record), date(record.transferred_at ?? record.created_at),
    ]),
    purchaseOrders: define('/purchase-orders', ['Código', 'Proveedor', 'Estado', 'Total previsto', 'Ordenada'], (record) => [
        text(record.code), text(record.supplier?.name), status(record), amount(record.expected_total), date(record.ordered_at),
    ]),
    goodsReceipts: define('/goods-receipts', ['Recepción', 'Orden', 'Estado de conciliación', 'Factura proveedor', 'Recibida'], (record) => [
        `#${text(record.id)}`, `#${text(record.purchase_order_id)}`, status(record, 'match_status_label', 'match_status'),
        text(record.supplier_invoice_number), date(record.received_at),
    ]),
    suppliers: define('/suppliers', ['Proveedor', 'Identificación fiscal', 'Estado', 'Correo', 'Teléfono'], (record) => [
        text(record.name), text(record.tax_id), status(record), text(record.email), text(record.phone),
    ], { search: true, searchLabel: 'Buscar proveedores registrados', searchPlaceholder: 'Buscar proveedor' }),
    payables: define('/accounts-payable', ['Cuenta', 'Proveedor', 'Estado', 'Saldo', 'Vencimiento'], (record) => [
        `#${text(record.id)}`, text(record.supplier?.name), status(record), amount(record.balance), date(record.due_date),
    ]),
    returns: define('/sales-returns', ['Código', 'Factura', 'Estado', 'Total devuelto', 'Fecha'], (record) => [
        text(record.code), `#${text(record.invoice_id)}`, status(record), amount(record.total_returned), date(record.returned_at),
    ]),
    creditNotes: define('/credit-notes', ['Folio', 'Factura', 'Estado', 'Resolución', 'Total'], (record) => [
        text(record.folio), `#${text(record.invoice_id)}`, status(record),
        text(record.resolution_label ?? record.resolution_type), amount(record.total_amount),
    ]),
});
