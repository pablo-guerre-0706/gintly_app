<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PhysicalCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PhysicalCount
 */
final class PhysicalCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'product_id'       => $this->product_id,
            'warehouse_id'     => $this->warehouse_id,
            'user_id'          => $this->user_id,
            'system_quantity'  => $this->system_quantity,
            'counted_quantity' => $this->counted_quantity,
            'difference'       => $this->difference,
            'status'           => $this->status->value,
            'status_label'     => $this->status->label(),
            'notes'            => $this->notes,
            'counted_at'       => $this->counted_at?->toIso8601String(),
            'product'          => new ProductResource($this->whenLoaded('product')),
            'warehouse'        => new WarehouseResource($this->whenLoaded('warehouse')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
