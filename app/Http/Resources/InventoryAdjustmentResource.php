<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InventoryAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryAdjustment
 */
final class InventoryAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'warehouse_id'      => $this->warehouse_id,
            'user_id'           => $this->user_id,
            'physical_count_id' => $this->physical_count_id,
            'type'              => $this->type->value,
            'type_label'        => $this->type->label(),
            'reason'            => $this->reason,
            'adjusted_at'       => $this->adjusted_at?->toIso8601String(),
            'warehouse'         => new WarehouseResource($this->whenLoaded('warehouse')),
            'movements'         => InventoryMovementResource::collection($this->whenLoaded('movements')),
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
