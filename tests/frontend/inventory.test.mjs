import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { assignedWarehouses, stockRecord, productUnit, countSnapshot, branchCollection, availabilityRecord, exceedsAvailable, minimumAlert } from '../../resources/js/modules/inventory/stock-contract.js';
import { authorizedNavigation, flattenedNavigation, NAVIGATION_GROUPS, isCurrentPageAuthorized } from '../../resources/js/shell/navigation.js';
import { canRetryInvoice, confirmedSale, invoicePayload } from '../../resources/js/modules/pos/invoice-recovery.js';

const count = { id: 1, counted_quantity: '9.000', system_quantity: '10.000', difference: '-1.000', status: 'abierto', status_label: 'Abierto', counted_at: '2026-10-01T12:00:00Z' };
const stock = { id: 1, product_id: 2, warehouse_id: 3, quantity: '25.000', reserved_quantity: '5.000', available: '20.000', last_count: count,
    product: { id: 2, name: 'Producto', sku: 'SKU', unit_id: 7, unit: { id: 7, name: 'Kilogramo', abbreviation: 'kg' } }, warehouse: { id: 3, branch_id: 4, name: 'Bodega' } };

test('diferencia histórica usa el sistema del mismo conteo, no el stock actual', () => {
    const row = stockRecord(stock);
    assert.equal(row.lastCount.difference, '-1.000');
    assert.equal(row.lastCount.system, '10.000');
    assert.equal(row.registered, '25.000');
    assert.throws(() => countSnapshot({ ...count, difference: '-16.000' }));
});
test('sin conteo no fabrica evidencia y la unidad proviene de product.unit', () => {
    const row = stockRecord({ ...stock, last_count: null });
    assert.equal(row.lastCount, null);
    assert.equal(row.unit, 'kg');
    assert.throws(() => stockRecord({ ...stock, product: { ...stock.product, unit: undefined } }));
});
test('unidad legible usa la abreviatura o el nombre real, nunca el ID técnico', () => {
    assert.equal(productUnit(stock.product), 'kg');
    assert.equal(productUnit({ ...stock.product, unit: { id: 7, name: 'Kilogramo', abbreviation: null } }), 'Kilogramo');
    assert.equal(productUnit({ ...stock.product, unit: { id: 7, name: 'Kilogramo', abbreviation: ' ' } }), 'Kilogramo');
    assert.throws(() => productUnit({ ...stock.product, unit: { id: 8, name: 'Otra unidad', abbreviation: 'x' } }));
    assert.throws(() => productUnit({ ...stock.product, unit: { id: 7, name: '', abbreviation: '' } }));
});
test('decimales contractuales inválidos fallan cerrado, sin redondeo silencioso', () => {
    assert.throws(() => stockRecord({ ...stock, quantity: '1.0001' }));
    assert.throws(() => stockRecord({ ...stock, available: 20 }));
});
test('bodeguero solo recibe asignaciones vigentes propias de su sucursal y sin duplicación', () => {
    const context = { identity: { id: 8 }, branch: { id: 4 } };
    const assignment = { active: true, user_id: 8, branch_id: 4, warehouse_id: 3, warehouse: { id: 3, branch_id: 4, name: 'A', is_active: true } };
    assert.equal(assignedWarehouses([assignment, assignment, { active: false }], context).length, 1);
    assert.throws(() => assignedWarehouses([{ ...assignment, user_id: 9 }], context));
    assert.throws(() => assignedWarehouses([{ ...assignment, branch_id: 5 }], context));
});
test('filtro administrativo por sucursal recorre todas las páginas de todas sus bodegas', async () => {
    const calls = [];
    const reader = async (path, query) => {
        calls.push({ path, ...query });
        return { data: [{ id: query.warehouse_id * 10 + query.page, warehouse_id: query.warehouse_id, updated_at: `2026-10-0${query.page}` }], meta: { current_page: query.page, last_page: 2, total: 2 } };
    };
    const rows = await branchCollection(reader, '/stock', [3, 4], { search: 'SKU' });
    assert.equal(rows.length, 4);
    assert.equal(calls.length, 4);
    assert.ok(calls.every((call) => !('branch_id' in call)));
    assert.deepEqual(rows.map((row) => row.id), [42, 32, 41, 31]);
});
test('disponibilidad decimal exacta no da acceso a costo ni infiere saldo ausente', () => {
    const available = availabilityRecord({ product_id: 2, warehouse_id: 3, quantity: '0.300', reserved_quantity: '0.200', available: '0.100', unit: 'kg' });
    assert.equal(exceedsAvailable('0.100', available), false);
    assert.equal(exceedsAvailable('0.101', available), true);
    assert.equal(available.cost, undefined);
});
test('aviso de mínimo usa su Resource explícito y no fabrica diferencias de conteo', () => {
    const alert = minimumAlert({ product_id: 2, warehouse_id: 3, product_name: 'Producto', sku: 'SKU',
        warehouse_name: 'Bodega', unit: null, available: '0.000', min_stock: '2.500' });
    assert.equal(alert.available, '0.000');
    assert.equal(alert.minimum, '2.500');
    assert.equal(alert.unit, null);
    assert.equal(alert.difference, undefined);
    assert.throws(() => minimumAlert({ product_id: 2, warehouse_id: 3 }));
});
test('consulta de inventario permitida a dirección y bodeguero, denegada a facturador aunque tenga una capacidad manipulada', () => {
    globalThis.window = { location: { origin: 'http://gintly.test' } };
    const urls = Object.fromEntries(NAVIGATION_GROUPS.flatMap((group) => group.items).map((item) => [item.urlKey, `http://gintly.test/${item.urlKey}`]));
    for (const role of ['ROL-01', 'ROL-02']) {
        const context = { role, profiles: [], capabilities: ['inventario.ver'] };
        assert.ok(flattenedNavigation(authorizedNavigation(context, urls)).some((item) => item.key === 'inventoryStock'));
        assert.equal(isCurrentPageAuthorized(context, urls, 'http://gintly.test/operations/stock'), false);
    }
    assert.equal(isCurrentPageAuthorized({ role: 'ROL-03', profiles: ['facturador'], capabilities: ['inventario.ver'] }, urls, 'http://gintly.test/inventory/stock'), false);
    assert.equal(isCurrentPageAuthorized({ role: 'ROL-03', profiles: ['bodeguero'], capabilities: ['inventario.ver'] }, urls, 'http://gintly.test/inventory/stock'), true);
});
test('consumidores usan fuentes limitadas, snapshots y carga modular', async () => {
    const source = await readFile(new URL('../../resources/js/modules/pos/availability.js', import.meta.url), 'utf8');
    assert.match(source, /allPages\(read, '\/sales\/availability'/);
    assert.doesNotMatch(source, /read\([^)]*['"]\/(?:stock|warehouses)/);
    const form = await readFile(new URL('../../resources/js/modules/operations/physical-count.js', import.meta.url), 'utf8');
    assert.match(form, /this\.scope\?\.warehouses\.some/);
    assert.match(form, /countSnapshot\(count\)/);
    assert.match(form, /productUnit\(item\)/);
    assert.match(form, /productUnit\(count\.product\)/);
    const scope = await readFile(new URL('../../resources/js/modules/inventory/stock-data.js', import.meta.url), 'utf8');
    assert.doesNotMatch(scope, /['"]\/units['"]/);
    const app = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    assert.match(app, /modules\/inventory\/stock\.js/);
    const lists = await readFile(new URL('../../resources/js/modules/operations/resource-config.js', import.meta.url), 'utf8');
    assert.doesNotMatch(lists, /(?:stock|warehouses): define/);
});
test('409 de insuficiencia permite solo reintento explícito, nunca red o persistencia incierta', () => {
    assert.equal(canRetryInvoice({ status: 409, code: 'INSUFFICIENT_STOCK' }), true);
    for (const status of [0, 403, 419, 422, 429, 500, 503]) assert.equal(canRetryInvoice({ status, code: 'INSUFFICIENT_STOCK' }), false);
    assert.equal(canRetryInvoice({ status: 409, code: 'OTHER_CONFLICT' }), false);
});
test('recuperación conserva venta y líneas confirmadas del mismo alcance, con importes exactos', () => {
    const sale = { id: 10, branch_id: 5, status: 'confirmada', items: [{ id: 20, sale_id: 10, line_total: '0.10', tax_amount: '0.02' }, { id: 21, sale_id: 10, line_total: '0.20', tax_amount: '0.03' }] };
    const real = confirmedSale({ data: sale }, 10, 5);
    assert.deepEqual(invoicePayload(real, 'transferencia'), { sale_ids: [10], payment_type: 'contado', payments: [{ method: 'transferencia', amount: '0.35', reference: null }] });
    assert.throws(() => confirmedSale({ data: { ...sale, status: 'facturada' } }, 10, 5));
    assert.throws(() => confirmedSale({ data: sale }, 11, 5));
    assert.throws(() => confirmedSale({ data: sale }, 10, 6));
    assert.throws(() => invoicePayload({ ...sale, items: [{ ...sale.items[0], line_total: undefined }] }, 'transferencia'));
});
