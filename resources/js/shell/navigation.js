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
    ] }),
    group({ key: 'sales-customers', label: 'Ventas y clientes', icon: 'fa-chart-line', items: [
        item({ key: 'salesSummary', label: 'Resumen de ventas', icon: 'fa-chart-column', urlKey: 'salesSummary', roles: OWNER, capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['ventas', 'historial', 'facturación', 'evolución']), activePaths: Object.freeze(['/sales/summary']) }),
        item({ key: 'adminInvoices', label: 'Supervisión de facturas', icon: 'fa-file-invoice-dollar', urlKey: 'adminInvoices', roles: ADMIN, capabilities: Object.freeze(['facturas.ver']), keywords: Object.freeze(['facturas', 'ventas', 'documentos']), activePaths: Object.freeze(['/administration/invoices']) }),
        item({ key: 'customers', label: 'Clientes', icon: 'fa-users', urlKey: 'customers', roles: HUMAN_ROLES, capabilities: Object.freeze(['clientes.ver']), keywords: Object.freeze(['clientes', 'cartera', 'crédito']), activePaths: Object.freeze(['/customers']), intent: 'shared' }),
        item({ key: 'pos', label: 'Punto de venta', icon: 'fa-cash-register', urlKey: 'pos', roles: OPERATOR, profiles: Object.freeze(['facturador']), capabilities: Object.freeze(['ventas.crear']), keywords: Object.freeze(['venta', 'pos', 'facturación']), activePaths: Object.freeze(['/pos']), intent: 'operation' }),
    ] }),
    group({ key: 'inventory-warehouse', label: 'Inventario y bodega', icon: 'fa-boxes-stacked', items: [
        item({ key: 'inventorySummary', label: 'Resumen de inventario', icon: 'fa-chart-pie', urlKey: 'inventorySummary', roles: OWNER, capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['inventario', 'exactitud', 'faltantes', 'desviaciones']), activePaths: Object.freeze(['/inventory/summary']) }),
        item({ key: 'adminCatalogProducts', label: 'Productos y catálogo', icon: 'fa-box-open', urlKey: 'catalogProducts', roles: ADMIN, capabilities: Object.freeze(['catalogo.ver']), keywords: Object.freeze(['productos', 'categorías', 'marcas', 'unidades']), activePaths: Object.freeze(['/catalog/products']), intent: 'administration' }),
        item({ key: 'operatorCatalogProducts', label: 'Productos y catálogo', icon: 'fa-box-open', urlKey: 'catalogProducts', roles: OPERATOR, profiles: Object.freeze(['facturador', 'bodeguero']), capabilities: Object.freeze(['catalogo.ver']), keywords: Object.freeze(['productos', 'catálogo', 'precios']), activePaths: Object.freeze(['/catalog/products']), intent: 'operation' }),
        item({ key: 'adminWarehouses', label: 'Bodegas', icon: 'fa-warehouse', urlKey: 'adminWarehouses', roles: ADMIN, capabilities: Object.freeze(['bodegas.ver']), keywords: Object.freeze(['bodegas', 'sucursales', 'almacenes']), activePaths: Object.freeze(['/administration/warehouses']), intent: 'administration' }),
        item({ key: 'adminPhysicalCounts', label: 'Conteos para validación', icon: 'fa-clipboard-check', urlKey: 'adminPhysicalCounts', roles: ADMIN, capabilities: Object.freeze(['inventario.conteo']), keywords: Object.freeze(['conteos', 'diferencias', 'validación']), activePaths: Object.freeze(['/administration/physical-counts']) }),
        item({ key: 'inventoryReconciliation', label: 'Conciliación operativa', icon: 'fa-scale-balanced', urlKey: 'inventoryReconciliation', roles: OPERATOR, profiles: Object.freeze(['bodeguero']), capabilities: Object.freeze(['inventario.conteo']), keywords: Object.freeze(['inventario', 'conciliación', 'ajuste']), activePaths: Object.freeze(['/inventory/reconciliation']), intent: 'operation' }),
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
        item({ key: 'adminCashRegisters', label: 'Cajas registradoras', icon: 'fa-cash-register', urlKey: 'adminCashRegisters', roles: ADMIN, capabilities: Object.freeze(['caja.gestionar']), keywords: Object.freeze(['cajas', 'terminales', 'sucursales']), activePaths: Object.freeze(['/administration/cash-registers']), intent: 'administration' }),
        item({ key: 'adminCashSessions', label: 'Sesiones y cierres', icon: 'fa-vault', urlKey: 'adminCashSessions', roles: ADMIN, anyCapability: Object.freeze(['caja.gestionar', 'caja.cerrar']), keywords: Object.freeze(['sesiones', 'cierres', 'contingencia', 'descuadre']), activePaths: Object.freeze(['/administration/cash-sessions']), intent: 'exception' }),
        item({ key: 'receivables', label: 'Cuentas por cobrar', icon: 'fa-hand-holding-dollar', urlKey: 'receivables', roles: OWNER, capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['cxc', 'cartera', 'vencida']), activePaths: Object.freeze(['/finance/receivables']) }),
        item({ key: 'payables', label: 'Cuentas por pagar', icon: 'fa-file-invoice', urlKey: 'payables', roles: DIRECTION, capabilities: Object.freeze(['cuentas_por_pagar.ver']), keywords: Object.freeze(['cxp', 'proveedores', 'obligaciones']), activePaths: Object.freeze(['/finance/payables']) }),
        item({ key: 'cashClosing', label: 'Cierre de caja', icon: 'fa-vault', urlKey: 'cashClosing', roles: OPERATOR, profiles: Object.freeze(['cajero']), capabilities: Object.freeze(['caja.cerrar']), keywords: Object.freeze(['sesiones', 'cierre', 'arqueo']), activePaths: Object.freeze(['/finance/cash-closing']), intent: 'operation' }),
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
        items: section.items.filter((entry) => urls[entry.urlKey] && isAuthorized(entry, context)).map((entry) => ({ ...entry, url: urls[entry.urlKey] })),
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
