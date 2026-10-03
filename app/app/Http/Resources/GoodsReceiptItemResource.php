<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GoodsReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceiptItem
 */
final class GoodsReceiptItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product_id'             => $this->product_id,
            'received_quantity'      => $this->received_quantity,
            'invoiced_unit_cost'     => $this->invoiced_unit_cost,
            'line_total'             => $this->line_total,
            'matched'                => $this->matched,
            'product'                => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
