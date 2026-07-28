<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DispatchItem */
final class DispatchItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'dispatch_id'  => $this->dispatch_id,
            'sale_item_id' => $this->sale_item_id,
            'product_id'   => $this->product_id,
            'quantity'     => (string) $this->quantity,
            'product'      => new ProductResource($this->whenLoaded('product')),
        ];
    }
}