const STATES = new Set(['pendiente', 'aprobado', 'suspendido']);
const fail = (field) => { throw new TypeError(`La respuesta de proveedores no contiene ${field} válido.`); };
const id = (value) => { if (!Number.isInteger(value) || value < 1) fail('identificador'); return value; };
const text = (value) => { if (typeof value !== 'string' || !value.trim()) fail('texto'); return value; };

export function coordinate(value, limit) {
    if (value === null || value === undefined || value === '') return null;
    if (!(typeof value === 'number' || (typeof value === 'string' && /^-?\d+(?:\.\d+)?$/.test(value.trim())))) fail('coordenada');
    const number = Number(value);
    if (!Number.isFinite(number) || Math.abs(number) > limit) fail('coordenada en rango');
    return number;
}

export function position(location) {
    const lat = coordinate(location.latitude, 90);
    const lng = coordinate(location.longitude, 180);
    if ((lat === null) !== (lng === null)) fail('par de coordenadas');
    return lat === null ? null : [lat, lng];
}

export function collection(payload) {
    if (!Array.isArray(payload?.data)) fail('colección data');
    return payload.data;
}

export function supplier(record) {
    id(record?.id); text(record.name);
    if (!STATES.has(record.status) || typeof record.is_active !== 'boolean') fail('estado del proveedor');
    for (const key of ['email', 'phone', 'tax_id']) if (record[key] !== null && typeof record[key] !== 'string') fail(key);
    return record;
}

export function location(record, supplierId) {
    id(record?.id); text(record.address);
    if (record.supplier_id !== supplierId || typeof record.is_primary !== 'boolean' || typeof record.confirmed !== 'boolean') fail('ubicación del proveedor');
    const point = position(record);
    if (record.confirmed && (!point || typeof record.confirmed_at !== 'string')) fail('confirmación');
    return record;
}

export function mapRecords(payload) {
    const keys = new Set();
    return collection(payload).map((record) => {
        id(record.id); text(record.name);
        if (record.status !== 'aprobado' || !Array.isArray(record.locations) || !record.locations.length) fail('proveedor aprobado con ubicaciones');
        const locations = record.locations.map((item) => {
            id(item.id); text(item.address);
            const point = position(item);
            const key = `${record.id}:${item.id}`;
            if (!point || typeof item.confirmed_at !== 'string' || !item.confirmed_at || typeof item.is_primary !== 'boolean' || keys.has(key)) fail('marcador confirmado único');
            keys.add(key);
            return { ...item, point, key, supplierId: record.id, supplierName: record.name };
        });
        return { ...record, locations };
    });
}

export function filteredMap(records, query = '', primaryOnly = false) {
    const normalize = (value) => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
    const needle = normalize(query.trim());
    return records.map((record) => ({ ...record, locations: record.locations.filter((item) =>
        (!primaryOnly || item.is_primary) && (!needle || normalize(`${record.name} ${item.address}`).includes(needle))) })).filter((record) => record.locations.length);
}

export function mapCounts(records) {
    return { suppliers: new Set(records.map((record) => record.id)).size, locations: new Set(records.flatMap((record) => record.locations.map((item) => item.key))).size };
}

export function supplierFocus(record) {
    // Only a visible primary or a single visible location can be selected
    // automatically. Multiple additional locations must be framed together.
    return record.locations.find((item) => item.is_primary) ?? (record.locations.length === 1 ? record.locations[0] : null);
}

export function supplierAccess(context) {
    const read = ['ROL-01', 'ROL-02', 'ROL-03'].includes(context?.role) && context.capabilities?.includes('proveedores.ver') === true;
    return { read, manage: read && ['ROL-01', 'ROL-02'].includes(context.role), approve: read && context.role === 'ROL-01' };
}
