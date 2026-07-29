<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SalesReturnItem */
final class SalesReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'sales_return_id'  => $this->sales_return_id,
            'product_id'       => $this->product_id,
            'sale_item_id'     => $this->sale_item_id,
            'warehouse_id'     => $this->warehouse_id,
            'quantity'         => (string) $this->quantity,
            'unit_price'       => (string) $this->unit_price,
            'line_total'       => (string) $this->line_total,
            'destination'      => $this->destination->value,
            'destination_label'=> $this->destination->label(),
            'reason_code'      => $this->reason_code->value,
            'reason_label'     => $this->reason_code->label(),

            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
