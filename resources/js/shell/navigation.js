const HUMAN_ROLES = Object.freeze(['ROL-01', 'ROL-02', 'ROL-03']);

export const NAVIGATION_GROUPS = Object.freeze([
    Object.freeze({
        label: 'Principal',
        items: Object.freeze([
            Object.freeze({
                key: 'dashboard',
                label: 'Dashboard',
                icon: 'fa-border-all',
                roles: HUMAN_ROLES,
                keywords: Object.freeze(['inicio', 'panel', 'resumen']),
            }),
        ]),
    }),
    Object.freeze({
        label: 'Operación',
        items: Object.freeze([
            Object.freeze({
                key: 'pos',
                label: 'Punto de venta',
                icon: 'fa-cash-register',
                anyCapability: Object.freeze(['ventas.crear']),
                keywords: Object.freeze(['venta', 'facturación', 'pos']),
            }),
            Object.freeze({
                key: 'cashClosing',
                label: 'Cierre de caja',
                icon: 'fa-vault',
                anyCapability: Object.freeze(['caja.cerrar']),
                keywords: Object.freeze(['caja', 'arqueo', 'cierre']),
            }),
            Object.freeze({
                key: 'customers',
                label: 'Clientes',
                icon: 'fa-users',
                anyCapability: Object.freeze(['clientes.ver']),
                keywords: Object.freeze(['cliente', 'fidelidad', 'cartera']),
            }),
            Object.freeze({
                key: 'customerCreate',
                label: 'Registrar cliente',
                icon: 'fa-user-plus',
                anyCapability: Object.freeze(['clientes.gestionar']),
                keywords: Object.freeze(['cliente', 'nuevo', 'registrar']),
            }),
            Object.freeze({
                key: 'inventoryReconciliation',
                label: 'Conciliación de inventario',
                icon: 'fa-boxes-packing',
                anyCapability: Object.freeze(['inventario.conteo']),
                keywords: Object.freeze(['inventario', 'conteo', 'stock', 'conciliación']),
            }),
            Object.freeze({
                key: 'catalogProducts',
                label: 'Catálogo de productos',
                icon: 'fa-boxes-stacked',
                anyCapability: Object.freeze(['catalogo.ver']),
                keywords: Object.freeze(['producto', 'catálogo', 'precio']),
            }),
        ]),
    }),
]);

function isAuthorized(item, context) {
    if (item.roles && !item.roles.includes(context.role)) {
        return false;
    }

    if (item.anyCapability && !item.anyCapability.some((capability) => (
        context.capabilities.includes(capability)
    ))) {
        return false;
    }

    return true;
}

export function authorizedNavigation(context, urls) {
    return NAVIGATION_GROUPS.map((group) => ({
        label: group.label,
        items: group.items
            .filter((item) => urls[item.key] && isAuthorized(item, context))
            .map((item) => ({ ...item, url: urls[item.key] })),
    })).filter((group) => group.items.length > 0);
}

export function flattenedNavigation(groups) {
    return groups.flatMap((group) => group.items.map((item) => ({
        ...item,
        group: group.label,
    })));
}
