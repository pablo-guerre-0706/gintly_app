<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceipt
 */
final class GoodsReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'purchase_order_id'       => $this->purchase_order_id,
            'warehouse_id'            => $this->warehouse_id,
            'user_id'                 => $this->user_id,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'supplier_invoice_total'  => $this->supplier_invoice_total,
            'match_status'            => $this->match_status->value,
            'match_status_label'      => $this->match_status->label(),
            'received_at'             => $this->received_at?->toIso8601String(),
            'notes'                   => $this->notes,
            'items'                   => GoodsReceiptItemResource::collection($this->whenLoaded('items')),
            'account_payable'         => new AccountPayableResource($this->whenLoaded('accountPayable')),
            'created_at'              => $this->created_at?->toIso8601String(),
        ];
    }
}
