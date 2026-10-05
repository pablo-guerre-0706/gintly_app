import { getSessionContext } from '@/core/session-context';
import { read } from '@/modules/operations/write-support';
import { allPages, assignedWarehouses, branchCollection, validatePage } from './stock-contract';

export async function inventoryScope(signal) {
    const context = await getSessionContext();
    if (!context.capabilities.includes('inventario.ver') || !['ROL-01', 'ROL-02', 'ROL-03'].includes(context.role)
        || (context.role === 'ROL-03' && !context.profiles.includes('bodeguero'))) throw new TypeError('El inventario no está disponible para tu cuenta.');
    if (context.role === 'ROL-03') {
        const assignments = await allPages(read, '/warehouse-assignments', { active: true }, signal);
        return { context, warehouses: assignedWarehouses(assignments, context), branches: [] };
    }
    const [warehouses, branches] = await Promise.all([
        allPages(read, '/warehouses', {}, signal), allPages(read, '/branches', {}, signal),
    ]);
    return { context, warehouses, branches };
}

export async function inventoryPage(path, scope, { branchId, warehouseId, page, ...query }, signal) {
    if (scope.context.role === 'ROL-03' && warehouseId && !scope.warehouses.some((warehouse) => warehouse.id === warehouseId)) {
        throw new TypeError('Esta bodega no está asignada a tu usuario.');
    }
    if (branchId && scope.context.role !== 'ROL-03' && !warehouseId) {
        const ids = scope.warehouses.filter((warehouse) => warehouse.branch_id === branchId).map((warehouse) => warehouse.id);
        const rows = await branchCollection(read, path, ids, query, signal);
        const perPage = 15;
        const lastPage = Math.max(1, Math.ceil(rows.length / perPage));
        const current = Math.min(page, lastPage);
        return { data: rows.slice((current - 1) * perPage, current * perPage), meta: { current_page: current, last_page: lastPage, total: rows.length } };
    }
    return validatePage(await read(path, { ...query, warehouse_id: warehouseId || undefined, per_page: 15, page }, signal));
}
