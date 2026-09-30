const HUMAN_ROLES = Object.freeze(['ROL-01', 'ROL-02', 'ROL-03']);

const item = (definition) => Object.freeze({
    capabilities: Object.freeze([]),
    anyCapability: Object.freeze([]),
    roles: Object.freeze([]),
    profiles: Object.freeze([]),
    keywords: Object.freeze([]),
    activePaths: Object.freeze([]),
    intent: 'supervision',
    ...definition,
});

const group = (definition) => Object.freeze({ items: Object.freeze(definition.items), ...definition });

/**
 * Registro único de presentación. `intent` separa supervisión, operación y
 * destinos compartidos sin replicar menús. Las Policies del Backend continúan
 * siendo la autoridad de seguridad.
 */
export const NAVIGATION_GROUPS = Object.freeze([
    group({
        key: 'dashboard', label: 'Dashboard', icon: 'fa-border-all', direct: true,
        items: [item({
            key: 'dashboard', label: 'Dashboard', icon: 'fa-border-all', urlKey: 'dashboard',
            roles: HUMAN_ROLES, keywords: Object.freeze(['inicio', 'panel', 'resumen', 'indicadores']),
            activePaths: Object.freeze(['/dashboard']), intent: 'shared',
        })],
    }),
    group({
        key: 'sales-customers', label: 'Ventas y clientes', icon: 'fa-chart-line',
        items: [
            item({ key: 'salesSummary', label: 'Resumen de ventas', icon: 'fa-chart-column', urlKey: 'salesSummary', roles: Object.freeze(['ROL-01']), capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['ventas', 'historial', 'facturación', 'evolución']), activePaths: Object.freeze(['/sales/summary']) }),
            item({ key: 'customers', label: 'Clientes', icon: 'fa-users', urlKey: 'customers', roles: HUMAN_ROLES, capabilities: Object.freeze(['clientes.ver']), keywords: Object.freeze(['clientes', 'cartera', 'crédito']), activePaths: Object.freeze(['/customers']), intent: 'shared' }),
            item({ key: 'pos', label: 'Punto de venta', icon: 'fa-cash-register', urlKey: 'pos', roles: Object.freeze(['ROL-03']), profiles: Object.freeze(['facturador']), capabilities: Object.freeze(['ventas.crear']), keywords: Object.freeze(['venta', 'pos', 'facturación']), activePaths: Object.freeze(['/pos']), intent: 'operation' }),
        ],
    }),
    group({
        key: 'inventory-warehouse', label: 'Inventario y bodega', icon: 'fa-boxes-stacked',
        items: [
            item({ key: 'inventorySummary', label: 'Resumen de inventario', icon: 'fa-chart-pie', urlKey: 'inventorySummary', roles: Object.freeze(['ROL-01']), capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['inventario', 'exactitud', 'faltantes', 'desviaciones']), activePaths: Object.freeze(['/inventory/summary']) }),
            item({ key: 'catalogProducts', label: 'Productos y catálogo', icon: 'fa-box-open', urlKey: 'catalogProducts', roles: Object.freeze(['ROL-02', 'ROL-03']), profiles: Object.freeze(['facturador', 'bodeguero']), capabilities: Object.freeze(['catalogo.ver']), keywords: Object.freeze(['productos', 'catálogo', 'precios']), activePaths: Object.freeze(['/catalog/products']), intent: 'operation' }),
            item({ key: 'inventoryReconciliation', label: 'Conciliación operativa', icon: 'fa-scale-balanced', urlKey: 'inventoryReconciliation', roles: Object.freeze(['ROL-02', 'ROL-03']), profiles: Object.freeze(['bodeguero']), anyCapability: Object.freeze(['inventario.conteo', 'conciliacion.ver']), keywords: Object.freeze(['inventario', 'conciliación', 'ajuste']), activePaths: Object.freeze(['/inventory/reconciliation']), intent: 'operation' }),
        ],
    }),
    group({
        key: 'purchases-suppliers', label: 'Compras y proveedores', icon: 'fa-truck-ramp-box',
        items: [
            item({ key: 'purchasesHub', label: 'Resumen de compras', icon: 'fa-table-cells-large', urlKey: 'purchasesHub', roles: Object.freeze(['ROL-01', 'ROL-02']), anyCapability: Object.freeze(['proveedores.ver', 'compras.ver', 'cuentas_por_pagar.ver']), keywords: Object.freeze(['compras', 'proveedores', 'resumen']), activePaths: Object.freeze(['/purchases']), exact: true }),
            item({ key: 'suppliers', label: 'Proveedores registrados', icon: 'fa-building', urlKey: 'suppliers', roles: Object.freeze(['ROL-01', 'ROL-02']), capabilities: Object.freeze(['proveedores.ver']), keywords: Object.freeze(['proveedores', 'registrados', 'directorio']), activePaths: Object.freeze(['/suppliers']), exact: true }),
            item({ key: 'externalSuppliers', label: 'Explorar proveedores', badge: 'Externo', icon: 'fa-map-location-dot', urlKey: 'externalSuppliers', roles: Object.freeze(['ROL-01', 'ROL-02']), keywords: Object.freeze(['mapa', 'proveedores', 'externos', 'cercanos']), activePaths: Object.freeze(['/suppliers/explore']) }),
        ],
    }),
    group({
        key: 'finance-credit', label: 'Finanzas y créditos', icon: 'fa-coins',
        items: [
            item({ key: 'financeHub', label: 'Resumen financiero', icon: 'fa-table-cells-large', urlKey: 'financeHub', roles: Object.freeze(['ROL-01', 'ROL-02']), anyCapability: Object.freeze(['reportes.ver', 'cuentas_por_cobrar.ver', 'cuentas_por_pagar.ver']), keywords: Object.freeze(['finanzas', 'créditos', 'resumen']), activePaths: Object.freeze(['/finance']), exact: true }),
            item({ key: 'cashOverview', label: 'Estado de cajas', icon: 'fa-cash-register', urlKey: 'cashOverview', roles: Object.freeze(['ROL-01']), capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['cajas', 'cierres', 'diferencias']), activePaths: Object.freeze(['/finance/cash-overview']) }),
            item({ key: 'receivables', label: 'Cuentas por cobrar', icon: 'fa-hand-holding-dollar', urlKey: 'receivables', roles: Object.freeze(['ROL-01']), capabilities: Object.freeze(['reportes.ver']), keywords: Object.freeze(['cxc', 'cartera', 'vencida']), activePaths: Object.freeze(['/finance/receivables']) }),
            item({ key: 'payables', label: 'Cuentas por pagar', icon: 'fa-file-invoice', urlKey: 'payables', roles: Object.freeze(['ROL-01', 'ROL-02']), capabilities: Object.freeze(['cuentas_por_pagar.ver']), keywords: Object.freeze(['cxp', 'proveedores', 'obligaciones']), activePaths: Object.freeze(['/finance/payables']) }),
            item({ key: 'cashClosing', label: 'Sesiones y cierres', icon: 'fa-vault', urlKey: 'cashClosing', roles: Object.freeze(['ROL-03']), profiles: Object.freeze(['cajero']), capabilities: Object.freeze(['caja.cerrar']), keywords: Object.freeze(['sesiones', 'cierre', 'arqueo']), activePaths: Object.freeze(['/finance/cash-closing']), intent: 'operation' }),
        ],
    }),
    group({
        key: 'intelligence-control', label: 'Inteligencia y control', icon: 'fa-chart-line',
        items: [
            item({ key: 'intelligenceHub', label: 'Resumen de control', icon: 'fa-table-cells-large', urlKey: 'intelligenceHub', roles: Object.freeze(['ROL-01', 'ROL-02']), anyCapability: Object.freeze(['metas.ver', 'reportes.ver', 'conciliacion.ver', 'anomalias.ver', 'auditoria.ver']), keywords: Object.freeze(['inteligencia', 'control', 'resumen']), activePaths: Object.freeze(['/intelligence']), exact: true }),
            item({ key: 'anomalies', label: 'Anomalías', icon: 'fa-triangle-exclamation', urlKey: 'anomalies', roles: Object.freeze(['ROL-01', 'ROL-02']), capabilities: Object.freeze(['anomalias.ver']), keywords: Object.freeze(['alertas', 'anomalías', 'excepciones']), activePaths: Object.freeze(['/intelligence/anomalies']) }),
            item({ key: 'audit', label: 'Auditoría', icon: 'fa-shield-halved', urlKey: 'audit', roles: Object.freeze(['ROL-01', 'ROL-02']), capabilities: Object.freeze(['auditoria.ver']), keywords: Object.freeze(['auditoría', 'bitácora', 'acciones']), activePaths: Object.freeze(['/intelligence/audit']) }),
        ],
    }),
    group({
        key: 'people-organization', label: 'Personal y organización', icon: 'fa-people-group',
        items: [
            item({ key: 'organizationHub', label: 'Resumen de organización', icon: 'fa-table-cells-large', urlKey: 'organizationHub', roles: Object.freeze(['ROL-01', 'ROL-02']), anyCapability: Object.freeze(['usuarios.ver', 'usuarios.gestionar', 'sucursales.gestionar']), keywords: Object.freeze(['personal', 'organización', 'resumen']), activePaths: Object.freeze(['/organization']), exact: true }),
            item({ key: 'users', label: 'Usuarios', icon: 'fa-user-gear', urlKey: 'users', roles: Object.freeze(['ROL-01', 'ROL-02']), capabilities: Object.freeze(['usuarios.ver']), keywords: Object.freeze(['usuarios', 'personal']), activePaths: Object.freeze(['/organization/users']) }),
            item({ key: 'profiles', label: 'Perfiles operativos ROL-03', icon: 'fa-id-badge', urlKey: 'profiles', roles: Object.freeze(['ROL-01', 'ROL-02']), capabilities: Object.freeze(['usuarios.gestionar']), keywords: Object.freeze(['perfiles', 'operativos', 'rol 03']), activePaths: Object.freeze(['/organization/profiles']) }),
            item({ key: 'branches', label: 'Sucursales', icon: 'fa-code-branch', urlKey: 'branches', roles: Object.freeze(['ROL-01', 'ROL-02']), capabilities: Object.freeze(['sucursales.gestionar']), keywords: Object.freeze(['sucursales', 'sedes']), activePaths: Object.freeze(['/organization/branches']) }),
        ],
    }),
    group({
        key: 'configuration', label: 'Configuración', icon: 'fa-gear',
        items: [item({ key: 'configurationHub', label: 'Resumen de configuración', icon: 'fa-table-cells-large', urlKey: 'configurationHub', roles: Object.freeze(['ROL-01', 'ROL-02']), anyCapability: Object.freeze(['negocio.ver', 'reglas_anomalia.ver']), keywords: Object.freeze(['configuración', 'negocio', 'reglas']), activePaths: Object.freeze(['/settings']) })],
    }),
    group({
        key: 'help', label: 'Centro de ayuda', icon: 'fa-circle-question', direct: true,
        items: [item({ key: 'help', label: 'Centro de ayuda', icon: 'fa-circle-question', urlKey: 'help', roles: HUMAN_ROLES, keywords: Object.freeze(['ayuda', 'soporte']), intent: 'shared' })],
    }),
]);

function normalizedPath(url) {
    try {
        const path = new URL(url, window.location.origin).pathname.replace(/\/+$/, '');
        return path || '/';
    } catch {
        return null;
    }
}

function matchesPath(entry, url, currentPath) {
    const paths = entry.activePaths.length > 0 ? entry.activePaths : [normalizedPath(url)];
    return paths.some((path) => path && (currentPath === path || (entry.exact !== true && currentPath.startsWith(`${path}/`))));
}

function isAuthorized(entry, context) {
    if (entry.intent === 'operation' && context.role === 'ROL-01') return false;
    if (entry.roles.length > 0 && !entry.roles.includes(context.role)) return false;
    if (entry.capabilities.length > 0 && !entry.capabilities.every((capability) => context.capabilities.includes(capability))) return false;
    if (entry.anyCapability.length > 0 && !entry.anyCapability.some((capability) => context.capabilities.includes(capability))) return false;
    if (context.role === 'ROL-03' && entry.profiles.length > 0 && !entry.profiles.some((profile) => context.profiles.includes(profile))) return false;
    return true;
}

export function authorizedNavigation(context, urls) {
    return NAVIGATION_GROUPS.map((section) => ({
        key: section.key,
        label: section.label,
        icon: section.icon,
        direct: section.direct === true,
        items: section.items
            .filter((entry) => urls[entry.urlKey] && isAuthorized(entry, context))
            .map((entry) => ({ ...entry, url: urls[entry.urlKey] })),
    })).filter((section) => section.items.length > 0);
}

export function isCurrentPageAuthorized(context, urls, href = window.location.href) {
    const currentPath = normalizedPath(href);
    const candidates = NAVIGATION_GROUPS.flatMap((section) => section.items)
        .filter((entry) => urls[entry.urlKey] && matchesPath(entry, urls[entry.urlKey], currentPath));
    return candidates.length > 0 && candidates.some((entry) => isAuthorized(entry, context));
}

export function flattenedNavigation(groups) {
    return groups.flatMap((section) => section.items.map((entry) => ({ ...entry, group: section.label })));
}
