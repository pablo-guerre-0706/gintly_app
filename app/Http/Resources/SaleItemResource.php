<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleItem
 */
final class SaleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'sale_id'             => $this->sale_id,
            'product_id'          => $this->product_id,
            'description'         => $this->description,
            'quantity'            => $this->quantity,
            'unit_price'          => $this->unit_price,
            'is_taxable'          => $this->is_taxable,
            'discount_amount'     => $this->discount_amount,
            'line_total'          => $this->line_total,
            'is_compound'         => $this->isCompound(),
            'dispatched_quantity' => $this->dispatched_quantity ?? '0.000',
            'returned_quantity'   => $this->returned_quantity ?? '0.000',
            'pending_quantity'    => $this->pendingQuantity(),
        ];
    }
}