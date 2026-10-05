import { read } from '@/modules/operations/write-support';
import { allPages, availabilityRecord, exceedsAvailable } from '@/modules/inventory/stock-contract';

/** Lectura limitada a la bodega que InvoiceService utilizará. No solicita /stock, /warehouses ni costos. */
export async function sellingAvailability(signal) {
    const records = await allPages(read, '/sales/availability', {}, signal);
    const rows = records.map(availabilityRecord);
    if (new Set(rows.map((row) => row.warehouseId)).size > 1) throw new TypeError('Disponibilidad contradictoria: más de una bodega de emisión.');
    return new Map(rows.map((row) => [row.productId, row]));
}

export function cartAvailability(cart, availability) {
    return [...cart.values()].flatMap(({ product, qty }) => {
        if (product.tracks_inventory !== true) return [];
        const row = availability.get(product.id);
        if (!row) return [`${product.name}: no existe saldo consultable en la bodega de emisión.`];
        return exceedsAvailable(qty, row) ? [`${product.name}: cantidad solicitada ${qty}; disponible ${row.available}.`] : [];
    });
}
