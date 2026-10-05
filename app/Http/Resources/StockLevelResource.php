<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockLevel
 */
final class StockLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'warehouse_id'      => $this->warehouse_id,
            'quantity'          => $this->quantity,
            'reserved_quantity' => $this->reserved_quantity,
            'available'         => $this->available,
            'average_cost'      => $this->average_cost,
            'min_stock'         => $this->min_stock,
            'max_stock'         => $this->max_stock,
            'below_min'         => $this->isBelowMin(),
            'available_below_min' => $this->isAvailableAtOrBelowMin(),
            // Último conteo físico del par (producto, bodega): saldo del sistema en ese momento, lo contado,
            // la diferencia y el estado de conciliación. NO es la existencia física actual (ver 'quantity').
            // La diferencia aquí NUNCA desaparece por umbral de anomalía: es el dato del conteo, no la anomalía.
            'last_count'        => $this->lastCountPayload(),
            'product'           => new ProductResource($this->whenLoaded('product')),
            'warehouse'         => new WarehouseResource($this->whenLoaded('warehouse')),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Último conteo físico adjuntado por el controlador (relación 'latestCount', posiblemente null).
     * Null explícito si el par aún no tiene conteos: se diferencia de "no cargado".
     *
     * @return array<string, mixed>|null
     */
    private function lastCountPayload(): ?array
    {
        if (! $this->resource->relationLoaded('latestCount')) {
            return null;
        }

        $count = $this->resource->getRelation('latestCount');

        if ($count === null) {
            return null;
        }

        return [
            'id'               => $count->id,
            'counted_quantity' => $count->counted_quantity,
            'system_quantity'  => $count->system_quantity,
            'difference'       => $count->difference,
            'status'           => $count->status->value,
            'status_label'     => $count->status->label(),
            'counted_at'       => $count->counted_at?->toIso8601String(),
        ];
    }
}
