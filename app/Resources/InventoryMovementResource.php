<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryMovement
 */
final class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->product_id,
            'warehouse_id'  => $this->warehouse_id,
            'user_id'       => $this->user_id,
            'type'          => $this->type->value,
            'type_label'    => $this->type->label(),
            'quantity'      => $this->quantity,
            'balance_after' => $this->balance_after,
            'unit_cost'     => $this->unit_cost,
            'origin'        => array_filter([
                'stock_transfer_id'       => $this->stock_transfer_id,
                'inventory_adjustment_id' => $this->inventory_adjustment_id,
                // 'purchase_order_id'    => $this->purchase_order_id,   // MOD-04
                // 'dispatch_id'          => $this->dispatch_id,         // MOD-09
            ], static fn ($v) => $v !== null),
            'reason'        => $this->reason,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
