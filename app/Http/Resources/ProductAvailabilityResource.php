<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MOD-03 (microcierre) · Disponibilidad para FACTURAR (selección de líneas). Expone existencia registrada,
 * reservado y disponible de la bodega predeterminada, con la unidad del producto. NO expone costo ni datos
 * generales de bodega: el facturador no recibe acceso de inventario para resolver esta consulta.
 *
 * @mixin StockLevel
 */
final class ProductAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id'        => $this->product_id,
            'sku'               => $this->product?->sku,
            'name'              => $this->product?->name,
            'unit'              => $this->product?->unit?->abbreviation,
            'warehouse_id'      => $this->warehouse_id,
            'quantity'          => $this->quantity,          // existencia registrada
            'reserved_quantity' => $this->reserved_quantity, // comprometido por facturas vivas
            'available'         => $this->available,          // disponible para vender (quantity − reserved)
        ];
    }
}
