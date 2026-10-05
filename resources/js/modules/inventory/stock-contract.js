import { compare, subtract, quantity } from '../../core/money.js';

const DECIMAL = /^-?\d+(?:\.\d{1,3})?$/;
const STATES = new Set(['abierto', 'justificado', 'ajustado']);

export function stockQuantity(value) {
    if (typeof value !== 'string' || !DECIMAL.test(value)) throw new TypeError('El inventario no devolvió un decimal de escala 3 válido.');
    return quantity(value);
}

export function countSnapshot(count) {
    if (count === null) return null;
    if (!count || !Number.isInteger(count.id) || !STATES.has(count.status) || typeof count.status_label !== 'string') {
        throw new TypeError('El conteo no contiene un estado contractual válido.');
    }
    const counted = stockQuantity(count.counted_quantity);
    const system = stockQuantity(count.system_quantity);
    const difference = stockQuantity(count.difference);
    if (compare(subtract(counted, system, 3), difference) !== 0) throw new TypeError('La diferencia del conteo contradice su saldo histórico.');
    return { id: count.id, counted, system, difference, status: count.status, label: count.status_label, date: count.counted_at };
}

export function productUnit(product) {
    const unit = product?.unit;
    if (!Number.isInteger(product?.unit_id) || unit?.id !== product.unit_id
        || typeof unit.name !== 'string'
        || (unit.abbreviation !== null && typeof unit.abbreviation !== 'string')) {
        throw new TypeError('El producto no incluye su unidad de medida contractual.');
    }
    const label = unit.abbreviation?.trim() || unit.name.trim();
    if (!label) throw new TypeError('La unidad de medida no tiene un nombre o abreviatura legible.');
    return label;
}

export function stockRecord(record) {
    if (!Number.isInteger(record.id) || !Number.isInteger(record.product_id) || !Number.isInteger(record.warehouse_id)
        || record.product?.id !== record.product_id || record.warehouse?.id !== record.warehouse_id
        || typeof record.product.name !== 'string' || typeof record.product.sku !== 'string'
        || !Number.isInteger(record.product.unit_id) || typeof record.warehouse.name !== 'string'
        || !Number.isInteger(record.warehouse.branch_id)) {
        throw new TypeError('El saldo no incluye las relaciones de producto y bodega esperadas.');
    }
    return {
        id: record.id, product: record.product.name, sku: record.product.sku,
        unit: productUnit(record.product),
        warehouse: record.warehouse.name, warehouseId: record.warehouse_id,
        branchId: record.warehouse.branch_id,
        registered: stockQuantity(record.quantity), reserved: stockQuantity(record.reserved_quantity),
        available: stockQuantity(record.available), lastCount: countSnapshot(record.last_count),
    };
}

export function assignedWarehouses(assignments, context) {
    const warehouses = new Map();
    for (const assignment of assignments) {
        if (assignment.active !== true) continue;
        if (assignment.user_id !== context.identity.id || assignment.branch_id !== context.branch.id
            || assignment.warehouse?.id !== assignment.warehouse_id || assignment.warehouse.branch_id !== context.branch.id) {
            throw new TypeError('La asignación de bodega contradice el usuario o la sucursal autenticada.');
        }
        if (assignment.warehouse.is_active === true) warehouses.set(assignment.warehouse_id, assignment.warehouse);
    }
    return [...warehouses.values()];
}

export function validatePage(payload) {
    const meta = payload?.meta;
    if (!Array.isArray(payload?.data) || !meta || !Number.isInteger(meta.current_page)
        || !Number.isInteger(meta.last_page) || !Number.isInteger(meta.total) || meta.last_page < meta.current_page) {
        throw new TypeError('El servidor no devolvió una colección paginada válida.');
    }
    return payload;
}

export async function allPages(readPage, path, query = {}, signal) {
    const result = [];
    let page = 1;
    let last = 1;
    do {
        const response = validatePage(await readPage(path, { ...query, per_page: 100, page }, signal));
        result.push(...response.data);
        last = response.meta.last_page;
        page += 1;
    } while (page <= last);
    return result;
}

/** branch_id no pertenece a /stock ni /physical-counts. Consulta completa de sus bodegas, sin filtrar una página parcial. */
export async function branchCollection(readPage, path, warehouseIds, query, signal) {
    const rows = [];
    for (const warehouseId of warehouseIds) {
        rows.push(...await allPages(readPage, path, { ...query, warehouse_id: warehouseId }, signal));
    }
    const dateKey = path === '/physical-counts' ? 'counted_at' : 'updated_at';
    return rows.sort((a, b) => String(b[dateKey]).localeCompare(String(a[dateKey])) || b.id - a.id);
}

export function availabilityRecord(record) {
    if (!Number.isInteger(record.product_id) || !Number.isInteger(record.warehouse_id)
        || (record.unit !== null && typeof record.unit !== 'string')) throw new TypeError('Disponibilidad sin producto, bodega o unidad contractuales.');
    return { productId: record.product_id, warehouseId: record.warehouse_id,
        registered: stockQuantity(record.quantity), reserved: stockQuantity(record.reserved_quantity), available: stockQuantity(record.available), unit: record.unit };
}

export function exceedsAvailable(requested, availability) {
    return compare(stockQuantity(requested), availability.available) > 0;
}

export function minimumAlert(record) {
    if (!Number.isInteger(record.product_id) || !Number.isInteger(record.warehouse_id)
        || typeof record.product_name !== 'string' || typeof record.sku !== 'string'
        || typeof record.warehouse_name !== 'string' || (record.unit !== null && typeof record.unit !== 'string')) {
        throw new TypeError('El aviso de mínimo no contiene los campos esperados.');
    }
    return { product: record.product_name, sku: record.sku, warehouse: record.warehouse_name, unit: record.unit,
        available: stockQuantity(record.available), minimum: stockQuantity(record.min_stock) };
}
