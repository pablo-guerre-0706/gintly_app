import { api } from '@/core/api-client';
import { read } from '@/modules/operations/write-support';
import { collection, mapRecords, supplier, location } from './contracts';

// Read recovery and form recovery each have ONE owner. Mutations are never retried here.
export const fetchMap = async (signal) => mapRecords(await read('/map/suppliers', {}, signal));
export const fetchLocations = async (supplierId, signal) => collection(await read(`/suppliers/${supplierId}/locations`, {}, signal)).map((row) => location(row, supplierId));
export async function fetchDirectory(query, signal) {
    const payload = await read('/suppliers', query, signal);
    collection(payload).forEach(supplier);
    if (!payload.links || !Number.isInteger(payload.meta?.current_page) || !Number.isInteger(payload.meta?.last_page) || !Number.isInteger(payload.meta?.total)) throw new TypeError('El directorio no devolvió paginación válida.');
    return payload;
}
export const createSupplier = async (payload) => {
    const record = supplier((await api.post('/suppliers', payload, { expectedStatus: 201, dispatchErrors: false })).data);
    if (record.status !== 'pendiente') throw new TypeError('El servidor no devolvió el candidato en estado pendiente. No repitas el registro; actualiza el directorio.');
    return record;
};
export const saveLocation = async (supplierId, locationId, payload) => {
    const path = `/suppliers/${supplierId}/locations${locationId ? `/${locationId}` : ''}`;
    const result = await api[locationId ? 'put' : 'post'](path, payload, { expectedStatus: locationId ? 200 : 201, dispatchErrors: false });
    return location(result.data, supplierId);
};
export const geocodeLocation = async (supplierId, locationId) => location((await api.post(`/suppliers/${supplierId}/locations/${locationId}/geocode`, {}, { expectedStatus: 200, dispatchErrors: false })).data, supplierId);
export const confirmLocation = async (supplierId, locationId, payload) => {
    const record = location((await api.post(`/suppliers/${supplierId}/locations/${locationId}/confirm`, payload, { expectedStatus: 200, dispatchErrors: false })).data, supplierId);
    if (!record.confirmed) throw new TypeError('El servidor no confirmó la ubicación. Actualiza sus datos antes de repetir la operación.');
    return record;
};
export const changeStatus = async (supplierId, action) => {
    if (!['approve', 'suspend'].includes(action)) throw new TypeError('Acción no disponible.');
    const record = supplier((await api.post(`/suppliers/${supplierId}/${action}`, {}, { expectedStatus: 200, dispatchErrors: false })).data);
    if (record.status !== (action === 'approve' ? 'aprobado' : 'suspendido')) throw new TypeError('El servidor no devolvió el estado solicitado. Actualiza el directorio antes de repetir la operación.');
    return record;
};
