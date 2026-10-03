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
            'product'           => new ProductResource($this->whenLoaded('product')),
            'warehouse'         => new WarehouseResource($this->whenLoaded('warehouse')),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
