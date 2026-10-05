import test from 'node:test';
import assert from 'node:assert/strict';
import { NAVIGATION_GROUPS, authorizedNavigation, flattenedNavigation, isCurrentPageAuthorized } from '../../resources/js/shell/navigation.js';

globalThis.window = { location: { origin: 'http://gintly.test' } };

const urls = Object.fromEntries(NAVIGATION_GROUPS.flatMap((group) => group.items.map((item) => [item.urlKey, `http://gintly.test/${item.urlKey}`])));
const keys = (context) => flattenedNavigation(authorizedNavigation(context, urls)).map((item) => item.key);
const operator = (profiles, capabilities, branchId = 1) => ({ role: 'ROL-03', profiles, capabilities, branch: { id: branchId } });

test('cajero consulta facturas, caja y cobros, sin POS ni compras', () => {
    const items = keys(operator(['cajero'], ['facturas.ver', 'clientes.ver', 'caja.abrir', 'caja.cerrar', 'cuentas_por_cobrar.ver']));
    assert.ok(items.includes('operativeInvoices'));
    assert.ok(items.includes('operativeCash'));
    assert.ok(items.includes('operativeReceivables'));
    assert.ok(!items.includes('pos'));
    assert.ok(!items.includes('operativePurchaseOrders'));
});

test('facturador obtiene ventas y POS, sin caja', () => {
    const items = keys(operator(['facturador'], ['ventas.ver', 'ventas.crear', 'facturas.ver', 'facturas.crear', 'catalogo.ver', 'clientes.ver']));
    assert.ok(items.includes('operativeSales'));
    assert.ok(items.includes('operativeInvoices'));
    assert.ok(items.includes('pos'));
    assert.ok(!items.includes('operativeCash'));
});

test('bodeguero obtiene lectura y flujos de bodega sin administración', () => {
    const items = keys(operator(['bodeguero'], ['bodegas.ver', 'inventario.ver', 'inventario.conteo', 'inventario.traspaso', 'compras.ver', 'compras.crear', 'compras.recibir', 'proveedores.ver', 'cuentas_por_pagar.ver', 'devoluciones.ver', 'devoluciones.crear', 'notas_credito.ver']));
    for (const key of ['operativeWarehouses', 'operativeStock', 'operativeTransfers', 'operativePurchaseOrders', 'operativeGoodsReceipts', 'operativeSuppliers', 'operativePayables', 'operativeReturns', 'operativeReturnsCreate', 'operativeCreditNotes']) assert.ok(items.includes(key), key);
    assert.ok(!items.includes('operativeCash'));
    assert.ok(!items.includes('pos'));
    assert.ok(!items.includes('users'));
});

test('despachador consulta facturas y despachos sin compras ni caja', () => {
    const items = keys(operator(['despachador'], ['facturas.ver', 'entregas.ver', 'entregas.crear']));
    assert.ok(items.includes('operativeInvoices'));
    assert.ok(items.includes('operativeDispatches'));
    assert.ok(!items.includes('operativePurchaseOrders'));
    assert.ok(!items.includes('operativeCash'));
});

test('multiperfil forma unión sin duplicar destinos y no infiere capacidades', () => {
    const items = keys(operator(['cajero', 'facturador', 'bodeguero', 'despachador'], ['facturas.ver', 'entregas.ver']));
    assert.equal(items.filter((key) => key === 'operativeInvoices').length, 1);
    assert.ok(!items.includes('pos'));
    assert.ok(!items.includes('operativeTransfers'));
});

test('acceso directo incompatible falla cerrado', () => {
    const context = operator(['cajero'], ['facturas.ver']);
    assert.equal(isCurrentPageAuthorized(context, urls, 'http://gintly.test/operations/stock-transfers'), false);
    assert.equal(isCurrentPageAuthorized(context, urls, 'http://gintly.test/operations/sales-returns/new'), false);
    assert.equal(isCurrentPageAuthorized(context, urls, 'http://gintly.test/operations/invoices'), true);
});

test('dirección y administración no adquieren rutas operativas', () => {
    for (const role of ['ROL-01', 'ROL-02']) {
        const items = keys({ role, profiles: [], capabilities: ['facturas.ver', 'compras.ver', 'inventario.traspaso', 'ventas.crear'] });
        assert.ok(!items.some((key) => key.startsWith('operative')));
        assert.ok(!items.includes('pos'));
    }
});

test('ROL-SYS y rol desconocido carecen de navegación', () => {
    assert.deepEqual(keys({ role: 'ROL-SYS', profiles: [], capabilities: ['facturas.ver'] }), []);
    assert.deepEqual(keys({ role: 'desconocido', profiles: [], capabilities: [] }), []);
});

test('sucursales: guard de creación y edición solo para dirección autorizada', () => {
    const pathCreate = 'http://gintly.test/organization/branches/create';
    const pathEdit = 'http://gintly.test/organization/branches/17/edit';
    for (const role of ['ROL-01', 'ROL-02']) {
        const context = { role, profiles: [], capabilities: ['sucursales.gestionar'] };
        assert.equal(isCurrentPageAuthorized(context, urls, pathCreate), true);
        assert.equal(isCurrentPageAuthorized(context, urls, pathEdit), true);
        assert.ok(keys(context).includes('branches'));
        assert.ok(!keys(context).includes('branchesCreateGuard'));
        assert.ok(!keys(context).includes('branchesEditGuard'));
    }
    const operatorContext = operator(['cajero'], ['sucursales.gestionar']);
    assert.equal(isCurrentPageAuthorized(operatorContext, urls, pathCreate), false);
    assert.equal(isCurrentPageAuthorized(operatorContext, urls, pathEdit), false);
    assert.ok(!keys(operatorContext).includes('branches'));
});

test('MOD-06 separa apertura, movimientos, arqueo, cierre e historial por capacidad', () => {
    const urlsCash = { ...urls,
        operativeCash: 'http://gintly.test/operations/cash',
        operativeCashOpen: 'http://gintly.test/operations/cash/open',
        operativeCashMovements: 'http://gintly.test/operations/cash/movements',
        operativeCashCount: 'http://gintly.test/operations/cash/count',
        operativeCashClose: 'http://gintly.test/operations/cash/close',
        operativeCashHistory: 'http://gintly.test/operations/cash/history',
    };
    const cashier = operator(['cajero'], ['caja.abrir', 'caja.cerrar', 'caja.movimiento.crear']);
    const cashierKeys = flattenedNavigation(authorizedNavigation(cashier, urlsCash)).map((entry) => entry.key);
    for (const key of ['operativeCash', 'operativeCashOpen', 'operativeCashMovements', 'operativeCashCount', 'operativeCashClose', 'operativeCashHistory']) assert.ok(cashierKeys.includes(key), key);
    const other = operator(['facturador'], ['caja.abrir', 'caja.cerrar', 'caja.movimiento.crear']);
    for (const path of ['/operations/cash/open', '/operations/cash/movements', '/operations/cash/count', '/operations/cash/close', '/operations/cash/history']) {
        assert.equal(isCurrentPageAuthorized(other, urlsCash, `http://gintly.test${path}`), false, path);
        assert.equal(isCurrentPageAuthorized(cashier, urlsCash, `http://gintly.test${path}`), true, path);
    }
});

test('tipo de cambio USD solo aparece en dirección con caja.gestionar', () => {
    const withRate = { ...urls, adminExchangeRates: 'http://gintly.test/administration/exchange-rates' };
    for (const role of ['ROL-01', 'ROL-02']) {
        const context = { role, profiles: [], capabilities: ['caja.gestionar'] };
        assert.ok(flattenedNavigation(authorizedNavigation(context, withRate)).some((item) => item.key === 'adminExchangeRates'));
        assert.equal(isCurrentPageAuthorized(context, withRate, withRate.adminExchangeRates), true);
    }
    assert.equal(isCurrentPageAuthorized(operator(['cajero'], ['caja.gestionar']), withRate, withRate.adminExchangeRates), false);
});
