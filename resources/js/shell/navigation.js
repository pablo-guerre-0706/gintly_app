const HUMAN_ROLES = Object.freeze(['ROL-01', 'ROL-02', 'ROL-03']);
const OWNER = Object.freeze(['ROL-01']);
const ADMIN = Object.freeze(['ROL-02']);
const OPERATOR = Object.freeze(['ROL-03']);
const DIRECTION = Object.freeze(['ROL-01', 'ROL-02']);

const item = (definition) => Object.freeze({
    capabilities: Object.freeze([]), anyCapability: Object.freeze([]), roles: Object.freeze([]),
    profiles: Object.freeze([]), keywords: Object.freeze([]), activePaths: Object.freeze([]),
    intent: 'supervision', ...definition,
});
const group = (definition) => Object.freeze({ items: Object.freeze(definition.items), ...definition });

/**
 * Registro único de presentación. `intent` evita que una capacidad amplia
 * convierta a dirección o administración en operador. Las Policies continúan
 * siendo la autoridad de seguridad.
 */
export const NAVIGATION_GROUPS = Object.freeze([
    group({ key: 'dashboard', label: 'Dashboard', icon: 'fa-border-all', direct: true, items: [
        item({ key: 'ownerDashboard', label: 'Dashboard', icon: 'fa-border-all', urlKey: 'dashboard', roles: OWNER, capabilities: Object.freeze(['panel.ver']), keywords: Object.freeze(['inicio', 'panel', 'dirección', 'indicadores']), activePaths: Object.freeze(['/dashboard']) }),
        item({ key: 'adminDashboard', label: 'Panel administrativo', icon: 'fa-border-all', urlKey: 'dashboard', roles: ADMIN, keywords: Object.freeze(['inicio', 'panel', 'administración', 'pendientes']), activePaths: Object.freeze(['/dashboard']), intent: 'administration' }),
        item({ key: 'operativeDashboard', label: 'Mi jornada', icon: 'fa-border-all', urlKey: 'dashboard', roles: OPERATOR, keywords: Object.freeze(['inicio', 'jornada', 'operación', 'pendientes']), activePaths: Object.freeze(['/dashboard']), intent: 'operation' }),
    ] }),
    group({ key: 'sales-customers', label: 'Ventas y clientes', icon: 'fa-chart-line', items: [
        item({ key: 'salesSummary', label: 'Resumen de ventas', icon: 'fa-chart-column', urlKey: 'salesSummary', roles: OWNER, capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['ventas', 'historial', 'facturación', 'evolución']), activePaths: Object.freeze(['/sales/summary']) }),
        item({ key: 'adminInvoices', label: 'Supervisión de facturas', icon: 'fa-file-invoice-dollar', urlKey: 'adminInvoices', roles: ADMIN, capabilities: Object.freeze(['facturas.ver']), keywords: Object.freeze(['facturas', 'ventas', 'documentos']), activePaths: Object.freeze(['/administration/invoices']) }),
        item({ key: 'customers', label: 'Clientes', icon: 'fa-users', urlKey: 'customers', roles: DIRECTION, capabilities: Object.freeze(['clientes.ver']), keywords: Object.freeze(['clientes', 'cartera', 'crédito']), activePaths: Object.freeze(['/customers']), intent: 'shared', exact: true }),
        item({ key: 'customersCreateGuard', label: 'Registrar cliente', icon: 'fa-user-plus', urlKey: 'customersCreate', roles: DIRECTION, capabilities: Object.freeze(['clientes.gestionar']), activePaths: Object.freeze(['/customers/create']), intent: 'administration', navigation: false }),
    ] }),
    group({ key: 'operative-sales', label: 'Ventas y facturación', icon: 'fa-file-invoice-dollar', items: [
        item({ key: 'pos', label: 'Punto de venta', icon: 'fa-cash-register', urlKey: 'pos', roles: OPERATOR, profiles: Object.freeze(['facturador']), capabilities: Object.freeze(['ventas.crear', 'facturas.crear', 'catalogo.ver', 'clientes.ver']), keywords: Object.freeze(['venta', 'pos', 'facturación']), activePaths: Object.freeze(['/pos']), intent: 'operation' }),
        item({ key: 'operatorCatalogProducts', label: 'Catálogo de productos', icon: 'fa-box-open', urlKey: 'catalogProducts', roles: OPERATOR, profiles: Object.freeze(['facturador']), capabilities: Object.freeze(['catalogo.ver']), keywords: Object.freeze(['productos', 'catálogo', 'precios']), activePaths: Object.freeze(['/catalog/products']), intent: 'operation' }),
        item({ key: 'operativeSales', label: 'Ventas', icon: 'fa-receipt', urlKey: 'operativeSales', roles: OPERATOR, profiles: Object.freeze(['facturador']), capabilities: Object.freeze(['ventas.ver']), keywords: Object.freeze(['ventas', 'estado', 'historial']), activePaths: Object.freeze(['/operations/sales']), intent: 'operation' }),
    ] }),
    group({ key: 'operative-shared', label: 'Consultas compartidas', icon: 'fa-file-invoice', items: [
        item({ key: 'operatorCustomers', label: 'Clientes', icon: 'fa-users', urlKey: 'customers', roles: OPERATOR, profiles: Object.freeze(['cajero', 'facturador']), capabilities: Object.freeze(['clientes.ver']), keywords: Object.freeze(['clientes', 'consulta']), activePaths: Object.freeze(['/customers']), intent: 'shared', exact: true }),
        item({ key: 'operativeInvoices', label: 'Facturas', icon: 'fa-file-invoice-dollar', urlKey: 'operativeInvoices', roles: OPERATOR, profiles: Object.freeze(['cajero', 'facturador', 'despachador']), capabilities: Object.freeze(['facturas.ver']), keywords: Object.freeze(['facturas', 'folios', 'consulta']), activePaths: Object.freeze(['/operations/invoices']), intent: 'shared' }),
    ] }),
    group({ key: 'inventory-warehouse', label: 'Inventario y bodega', icon: 'fa-boxes-stacked', items: [
        item({ key: 'inventorySummary', label: 'Resumen de inventario', icon: 'fa-chart-pie', urlKey: 'inventorySummary', roles: OWNER, capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['inventario', 'exactitud', 'faltantes', 'desviaciones']), activePaths: Object.freeze(['/inventory/summary']) }),
        item({ key: 'adminCatalogProducts', label: 'Productos y catálogo', icon: 'fa-box-open', urlKey: 'catalogProducts', roles: ADMIN, capabilities: Object.freeze(['catalogo.ver']), keywords: Object.freeze(['productos', 'categorías', 'marcas', 'unidades']), activePaths: Object.freeze(['/catalog/products']), intent: 'administration' }),
        item({ key: 'operativeStock', label: 'Existencias', icon: 'fa-boxes-stacked', urlKey: 'operativeStock', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['inventario.ver']), keywords: Object.freeze(['existencias', 'stock', 'bodega']), activePaths: Object.freeze(['/operations/stock']), intent: 'operation' }),
        item({ key: 'operativePhysicalCount', label: 'Registrar conteo', icon: 'fa-clipboard-list', urlKey: 'operativePhysicalCount', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['inventario.conteo']), keywords: Object.freeze(['conteo', 'captura', 'inventario']), activePaths: Object.freeze(['/operations/physical-counts/new']), intent: 'operation' }),
        item({ key: 'operativeWarehouses', label: 'Bodegas', icon: 'fa-warehouse', urlKey: 'operativeWarehouses', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['bodegas.ver']), keywords: Object.freeze(['bodegas', 'almacenes']), activePaths: Object.freeze(['/operations/warehouses']), intent: 'operation' }),
        item({ key: 'operativeTransfers', label: 'Traspasos', icon: 'fa-right-left', urlKey: 'operativeTransfers', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['inventario.traspaso']), keywords: Object.freeze(['traspasos', 'existencias']), activePaths: Object.freeze(['/operations/stock-transfers']), intent: 'operation', exact: true }),
        item({ key: 'operativeTransfersCreate', label: 'Nuevo traspaso', icon: 'fa-right-left', urlKey: 'operativeTransfersCreate', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['inventario.traspaso']), keywords: Object.freeze(['traspaso', 'crear']), activePaths: Object.freeze(['/operations/stock-transfers/new']), intent: 'operation', exact: true }),
        item({ key: 'adminWarehouses', label: 'Bodegas', icon: 'fa-warehouse', urlKey: 'adminWarehouses', roles: DIRECTION, capabilities: Object.freeze(['bodegas.ver', 'bodegas.gestionar']), keywords: Object.freeze(['bodegas', 'sucursales', 'almacenes']), activePaths: Object.freeze(['/administration/warehouses']), intent: 'administration' }),
        item({ key: 'adminPhysicalCounts', label: 'Conteos para validación', icon: 'fa-clipboard-check', urlKey: 'adminPhysicalCounts', roles: ADMIN, capabilities: Object.freeze(['inventario.conteo']), keywords: Object.freeze(['conteos', 'diferencias', 'validación']), activePaths: Object.freeze(['/administration/physical-counts']) }),
    ] }),
    group({ key: 'operative-purchases', label: 'Compras y recepción', icon: 'fa-truck-ramp-box', items: [
        item({ key: 'operativePurchaseOrders', label: 'Órdenes de compra', icon: 'fa-file-circle-check', urlKey: 'operativePurchaseOrders', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['compras.ver']), keywords: Object.freeze(['compras', 'órdenes']), activePaths: Object.freeze(['/operations/purchase-orders']), intent: 'operation', exact: true }),
        item({ key: 'operativePurchaseOrdersCreate', label: 'Nuevo borrador', icon: 'fa-file-circle-plus', urlKey: 'operativePurchaseOrdersCreate', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['compras.crear']), keywords: Object.freeze(['orden', 'compra', 'crear', 'borrador']), activePaths: Object.freeze(['/operations/purchase-orders/new']), intent: 'operation', exact: true }),
        item({ key: 'operativeGoodsReceipts', label: 'Recepciones', icon: 'fa-boxes-packing', urlKey: 'operativeGoodsReceipts', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['compras.ver']), keywords: Object.freeze(['recepciones', 'mercancía']), activePaths: Object.freeze(['/operations/goods-receipts']), intent: 'operation', exact: true }),
        item({ key: 'operativeGoodsReceiptsCreate', label: 'Registrar recepción', icon: 'fa-box-open', urlKey: 'operativeGoodsReceiptsCreate', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['compras.recibir']), keywords: Object.freeze(['recepción', 'ingreso', 'mercancía']), activePaths: Object.freeze(['/operations/goods-receipts/new']), intent: 'operation', exact: true }),
        item({ key: 'operativeSuppliers', label: 'Proveedores', icon: 'fa-building', urlKey: 'operativeSuppliers', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['proveedores.ver']), keywords: Object.freeze(['proveedores', 'registrados']), activePaths: Object.freeze(['/operations/suppliers']), intent: 'operation' }),
        item({ key: 'operativePayables', label: 'Cuentas por pagar', icon: 'fa-file-invoice', urlKey: 'operativePayables', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['cuentas_por_pagar.ver']), keywords: Object.freeze(['cxp', 'saldos']), activePaths: Object.freeze(['/operations/accounts-payable']), intent: 'operation' }),
    ] }),
    group({ key: 'operative-returns', label: 'Devoluciones', icon: 'fa-rotate-left', items: [
        item({ key: 'operativeReturns', label: 'Devoluciones', icon: 'fa-rotate-left', urlKey: 'operativeReturns', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['devoluciones.ver']), keywords: Object.freeze(['devoluciones', 'facturas']), activePaths: Object.freeze(['/operations/sales-returns']), intent: 'operation', exact: true }),
        item({ key: 'operativeReturnsCreate', label: 'Registrar devolución', icon: 'fa-rotate-left', urlKey: 'operativeReturnsCreate', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['devoluciones.crear']), keywords: Object.freeze(['devoluciones', 'registrar', 'productos']), activePaths: Object.freeze(['/operations/sales-returns/new']), intent: 'operation', exact: true }),
        item({ key: 'operativeCreditNotes', label: 'Notas de crédito', icon: 'fa-file-circle-minus', urlKey: 'operativeCreditNotes', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['notas_credito.ver']), keywords: Object.freeze(['notas de crédito', 'resoluciones']), activePaths: Object.freeze(['/operations/credit-notes']), intent: 'operation' }),
    ] }),
    group({ key: 'purchases-suppliers', label: 'Compras y proveedores', icon: 'fa-truck-ramp-box', items: [
        item({ key: 'purchasesHub', label: 'Resumen de compras', icon: 'fa-table-cells-large', urlKey: 'purchasesHub', roles: DIRECTION, anyCapability: Object.freeze(['proveedores.ver', 'compras.ver', 'cuentas_por_pagar.ver']), keywords: Object.freeze(['compras', 'proveedores', 'resumen']), activePaths: Object.freeze(['/purchases']), exact: true }),
        item({ key: 'suppliers', label: 'Proveedores registrados', icon: 'fa-building', urlKey: 'suppliers', roles: DIRECTION, capabilities: Object.freeze(['proveedores.ver']), keywords: Object.freeze(['proveedores', 'registrados', 'directorio']), activePaths: Object.freeze(['/suppliers']), exact: true }),
        item({ key: 'adminPurchaseOrders', label: 'Órdenes de compra', icon: 'fa-file-circle-check', urlKey: 'adminPurchaseOrders', roles: ADMIN, capabilities: Object.freeze(['compras.ver']), keywords: Object.freeze(['órdenes', 'compras', 'emisión']), activePaths: Object.freeze(['/administration/purchase-orders']), intent: 'administration' }),
        item({ key: 'adminGoodsReceipts', label: 'Recepciones', icon: 'fa-boxes-packing', urlKey: 'adminGoodsReceipts', roles: ADMIN, capabilities: Object.freeze(['compras.ver']), keywords: Object.freeze(['recepciones', 'discrepancias', '3-way']), activePaths: Object.freeze(['/administration/goods-receipts']) }),
        item({ key: 'externalSuppliers', label: 'Explorar proveedores', badge: 'Externo', icon: 'fa-map-location-dot', urlKey: 'externalSuppliers', roles: DIRECTION, keywords: Object.freeze(['mapa', 'proveedores', 'externos', 'cercanos']), activePaths: Object.freeze(['/suppliers/explore']) }),
    ] }),
    group({ key: 'finance-credit', label: 'Finanzas y créditos', icon: 'fa-coins', items: [
        item({ key: 'financeHub', label: 'Resumen financiero', icon: 'fa-table-cells-large', urlKey: 'financeHub', roles: DIRECTION, anyCapability: Object.freeze(['reportes.ver', 'cuentas_por_cobrar.ver', 'cuentas_por_pagar.ver', 'caja.gestionar']), keywords: Object.freeze(['finanzas', 'créditos', 'resumen']), activePaths: Object.freeze(['/finance']), exact: true }),
        item({ key: 'cashOverview', label: 'Estado de cajas', icon: 'fa-cash-register', urlKey: 'cashOverview', roles: OWNER, capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['cajas', 'cierres', 'diferencias']), activePaths: Object.freeze(['/finance/cash-overview']) }),
        item({ key: 'adminCashRegisters', label: 'Cajas registradoras', icon: 'fa-cash-register', urlKey: 'adminCashRegisters', roles: DIRECTION, capabilities: Object.freeze(['caja.gestionar']), keywords: Object.freeze(['cajas', 'terminales', 'sucursales']), activePaths: Object.freeze(['/administration/cash-registers']), intent: 'administration' }),
        item({ key: 'adminCashSessions', label: 'Historial de sesiones', icon: 'fa-vault', urlKey: 'adminCashSessions', roles: DIRECTION, capabilities: Object.freeze(['caja.gestionar']), keywords: Object.freeze(['sesiones', 'cierres', 'contingencia', 'descuadre']), activePaths: Object.freeze(['/administration/cash-sessions']), intent: 'exception' }),
        item({ key: 'adminExchangeRates', label: 'Tipo de cambio USD', icon: 'fa-money-bill-transfer', urlKey: 'adminExchangeRates', roles: DIRECTION, capabilities: Object.freeze(['caja.gestionar']), keywords: Object.freeze(['dólar', 'tasa', 'usd', 'cambio']), activePaths: Object.freeze(['/administration/exchange-rates']), intent: 'administration' }),
        item({ key: 'receivables', label: 'Cuentas por cobrar', icon: 'fa-hand-holding-dollar', urlKey: 'receivables', roles: OWNER, capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['cxc', 'cartera', 'vencida']), activePaths: Object.freeze(['/finance/receivables']) }),
        item({ key: 'payables', label: 'Cuentas por pagar', icon: 'fa-file-invoice', urlKey: 'payables', roles: DIRECTION, capabilities: Object.freeze(['cuentas_por_pagar.ver']), keywords: Object.freeze(['cxp', 'proveedores', 'obligaciones']), activePaths: Object.freeze(['/finance/payables']) }),
    ] }),
    group({ key: 'cash-collections', label: 'Caja y cobros', icon: 'fa-cash-register', items: [
        item({ key: 'operativeCash', label: 'Mi caja', icon: 'fa-cash-register', urlKey: 'operativeCash', roles: OPERATOR, profiles: Object.freeze(['cajero']), anyCapability: Object.freeze(['caja.abrir', 'caja.movimiento.crear']), keywords: Object.freeze(['caja', 'sesión']), activePaths: Object.freeze(['/operations/cash']), intent: 'operation', exact: true }),
        item({ key: 'operativeCashOpen', label: 'Apertura de caja', icon: 'fa-door-open', urlKey: 'operativeCashOpen', roles: OPERATOR, profiles: Object.freeze(['cajero']), capabilities: Object.freeze(['caja.abrir']), keywords: Object.freeze(['apertura', 'fondo', 'nio', 'usd']), activePaths: Object.freeze(['/operations/cash/open']), intent: 'operation', exact: true }),
        item({ key: 'operativeCashMovements', label: 'Movimientos', icon: 'fa-arrow-right-arrow-left', urlKey: 'operativeCashMovements', roles: OPERATOR, profiles: Object.freeze(['cajero']), capabilities: Object.freeze(['caja.movimiento.crear']), keywords: Object.freeze(['ingreso', 'egreso', 'movimientos']), activePaths: Object.freeze(['/operations/cash/movements']), intent: 'operation', exact: true }),
        item({ key: 'operativeCashCount', label: 'Arqueo independiente', icon: 'fa-list-check', urlKey: 'operativeCashCount', roles: OPERATOR, profiles: Object.freeze(['cajero']), capabilities: Object.freeze(['caja.movimiento.crear']), keywords: Object.freeze(['conteo', 'arqueo', 'denominaciones']), activePaths: Object.freeze(['/operations/cash/count']), intent: 'operation', exact: true }),
        item({ key: 'operativeCashClose', label: 'Cierre de caja', icon: 'fa-vault', urlKey: 'operativeCashClose', roles: OPERATOR, profiles: Object.freeze(['cajero']), capabilities: Object.freeze(['caja.cerrar']), keywords: Object.freeze(['cierre', 'denominaciones']), activePaths: Object.freeze(['/operations/cash/close']), intent: 'operation', exact: true }),
        item({ key: 'operativeCashHistory', label: 'Mis aperturas y cierres', icon: 'fa-clock-rotate-left', urlKey: 'operativeCashHistory', roles: OPERATOR, profiles: Object.freeze(['cajero']), anyCapability: Object.freeze(['caja.abrir', 'caja.cerrar']), keywords: Object.freeze(['historial', 'sesiones', 'aperturas', 'cierres']), activePaths: Object.freeze(['/operations/cash/history']), intent: 'operation', exact: true }),
        item({ key: 'operativeReceivables', label: 'Cobros pendientes', icon: 'fa-hand-holding-dollar', urlKey: 'operativeReceivables', roles: OPERATOR, profiles: Object.freeze(['cajero']), capabilities: Object.freeze(['cuentas_por_cobrar.ver']), keywords: Object.freeze(['cobros', 'cxc', 'abonos', 'cartera']), activePaths: Object.freeze(['/operations/receivables']), intent: 'operation' }),
    ] }),
    group({ key: 'dispatch-deliveries', label: 'Despachos y entregas', icon: 'fa-truck-fast', items: [
        item({ key: 'operativeDispatches', label: 'Despachos', icon: 'fa-truck-fast', urlKey: 'operativeDispatches', roles: OPERATOR, profiles: Object.freeze(['despachador']), capabilities: Object.freeze(['entregas.ver']), keywords: Object.freeze(['despachos', 'entregas', 'retiros']), activePaths: Object.freeze(['/operations/dispatches']), intent: 'operation' }),
    ] }),
    group({ key: 'intelligence-control', label: 'Inteligencia y control', icon: 'fa-chart-line', items: [
        item({ key: 'intelligenceHub', label: 'Resumen de control', icon: 'fa-table-cells-large', urlKey: 'intelligenceHub', roles: DIRECTION, anyCapability: Object.freeze(['metas.ver', 'reportes.ver', 'conciliacion.ver', 'anomalias.ver', 'auditoria.ver', 'kpis.ver']), keywords: Object.freeze(['inteligencia', 'control', 'resumen']), activePaths: Object.freeze(['/intelligence']), exact: true }),
        item({ key: 'anomalies', label: 'Anomalías', icon: 'fa-triangle-exclamation', urlKey: 'anomalies', roles: DIRECTION, capabilities: Object.freeze(['anomalias.ver']), keywords: Object.freeze(['alertas', 'anomalías', 'justificación', 'excepciones']), activePaths: Object.freeze(['/intelligence/anomalies']) }),
        item({ key: 'reconciliations', label: 'Conciliaciones', icon: 'fa-scale-balanced', urlKey: 'reconciliations', roles: ADMIN, capabilities: Object.freeze(['conciliacion.ver']), keywords: Object.freeze(['conciliación', 'manual', 'anomalías']), activePaths: Object.freeze(['/intelligence/reconciliations']), intent: 'exception' }),
        item({ key: 'kpiSnapshots', label: 'Instantáneas KPI', icon: 'fa-chart-simple', urlKey: 'kpiSnapshots', roles: ADMIN, capabilities: Object.freeze(['kpis.ver']), keywords: Object.freeze(['kpi', 'indicadores', 'instantáneas']), activePaths: Object.freeze(['/intelligence/kpi-snapshots']) }),
        item({ key: 'reportDefinitions', label: 'Definiciones de reportes', icon: 'fa-file-lines', urlKey: 'reportDefinitions', roles: ADMIN, capabilities: Object.freeze(['definiciones_reporte.gestionar']), keywords: Object.freeze(['reportes', 'definiciones', 'filtros']), activePaths: Object.freeze(['/intelligence/report-definitions']), intent: 'administration' }),
        item({ key: 'audit', label: 'Auditoría', icon: 'fa-shield-halved', urlKey: 'audit', roles: DIRECTION, capabilities: Object.freeze(['auditoria.ver']), keywords: Object.freeze(['auditoría', 'bitácora', 'acciones']), activePaths: Object.freeze(['/intelligence/audit']) }),
    ] }),
    group({ key: 'people-organization', label: 'Personal y organización', icon: 'fa-people-group', items: [
        item({ key: 'organizationHub', label: 'Resumen de organización', icon: 'fa-table-cells-large', urlKey: 'organizationHub', roles: DIRECTION, anyCapability: Object.freeze(['usuarios.ver', 'usuarios.gestionar', 'sucursales.gestionar']), keywords: Object.freeze(['personal', 'organización', 'resumen']), activePaths: Object.freeze(['/organization']), exact: true }),
        item({ key: 'users', label: 'Usuarios', icon: 'fa-user-gear', urlKey: 'users', roles: DIRECTION, capabilities: Object.freeze(['usuarios.ver']), keywords: Object.freeze(['usuarios', 'personal']), activePaths: Object.freeze(['/organization/users']) }),
        item({ key: 'profiles', label: 'Perfiles operativos ROL-03', icon: 'fa-id-badge', urlKey: 'profiles', roles: DIRECTION, capabilities: Object.freeze(['usuarios.gestionar']), keywords: Object.freeze(['perfiles', 'operativos', 'rol 03']), activePaths: Object.freeze(['/organization/profiles']) }),
        item({ key: 'branches', label: 'Sucursales', icon: 'fa-code-branch', urlKey: 'branches', roles: DIRECTION, capabilities: Object.freeze(['sucursales.gestionar']), keywords: Object.freeze(['sucursales', 'sedes']), activePaths: Object.freeze(['/organization/branches']) }),
        item({ key: 'branchesCreateGuard', label: 'Crear sucursal', urlKey: 'branchesCreate', roles: DIRECTION, capabilities: Object.freeze(['sucursales.gestionar']), activePaths: Object.freeze(['/organization/branches/create']), intent: 'administration', navigation: false, exact: true }),
        item({ key: 'branchesEditGuard', label: 'Editar sucursal', urlKey: 'branchesEdit', roles: DIRECTION, capabilities: Object.freeze(['sucursales.gestionar']), activePaths: Object.freeze(['/organization/branches']), intent: 'administration', navigation: false }),
    ] }),
    group({ key: 'configuration', label: 'Configuración', icon: 'fa-gear', items: [
        item({ key: 'configurationHub', label: 'Resumen de configuración', icon: 'fa-table-cells-large', urlKey: 'configurationHub', roles: DIRECTION, anyCapability: Object.freeze(['negocio.ver', 'reglas_anomalia.ver']), keywords: Object.freeze(['configuración', 'negocio', 'reglas']), activePaths: Object.freeze(['/settings']) }),
    ] }),
    group({ key: 'help', label: 'Centro de ayuda', icon: 'fa-circle-question', direct: true, items: [
        item({ key: 'help', label: 'Centro de ayuda', icon: 'fa-circle-question', urlKey: 'help', roles: HUMAN_ROLES, keywords: Object.freeze(['ayuda', 'soporte']), intent: 'shared' }),
    ] }),
]);

function normalizedPath(url) {
    try {
        const path = new URL(url, window.location.origin).pathname.replace(/\/+$/, '');
        return path || '/';
    } catch { return null; }
}

function matchesPath(entry, url, currentPath) {
    const paths = entry.activePaths.length > 0 ? entry.activePaths : [normalizedPath(url)];
    return paths.some((path) => path && (currentPath === path || (entry.exact !== true && currentPath.startsWith(`${path}/`))));
}

function isAuthorized(entry, context) {
    if (entry.intent === 'operation' && context.role !== 'ROL-03') return false;
    if (entry.roles.length > 0 && !entry.roles.includes(context.role)) return false;
    if (entry.capabilities.length > 0 && !entry.capabilities.every((capability) => context.capabilities.includes(capability))) return false;
    if (entry.anyCapability.length > 0 && !entry.anyCapability.some((capability) => context.capabilities.includes(capability))) return false;
    if (context.role === 'ROL-03' && entry.profiles.length > 0 && !entry.profiles.some((profile) => context.profiles.includes(profile))) return false;
    return true;
}

export function authorizedNavigation(context, urls) {
    return NAVIGATION_GROUPS.map((section) => ({
        key: section.key, label: section.label, icon: section.icon, direct: section.direct === true,
        items: section.items.filter((entry) => entry.navigation !== false && urls[entry.urlKey] && isAuthorized(entry, context)).map((entry) => ({ ...entry, url: urls[entry.urlKey] })),
    })).filter((section) => section.items.length > 0);
}

export function isCurrentPageAuthorized(context, urls, href = window.location.href) {
    const currentPath = normalizedPath(href);
    const candidates = NAVIGATION_GROUPS.flatMap((section) => section.items).filter((entry) => urls[entry.urlKey] && matchesPath(entry, urls[entry.urlKey], currentPath));
    return candidates.length > 0 && candidates.some((entry) => isAuthorized(entry, context));
}

export function flattenedNavigation(groups) {
    return groups.flatMap((section) => section.items.map((entry) => ({ ...entry, group: section.label })));
}
