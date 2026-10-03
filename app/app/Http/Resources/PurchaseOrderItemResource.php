<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderItem
 */
final class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'ordered_quantity'  => $this->ordered_quantity,
            'received_quantity' => $this->received_quantity,
            'pending_quantity'  => $this->pendingQuantity(),
            'agreed_unit_cost'  => $this->agreed_unit_cost,
            'line_total'        => $this->line_total,
            'product'           => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
