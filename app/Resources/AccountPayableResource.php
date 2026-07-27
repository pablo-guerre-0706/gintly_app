<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AccountPayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountPayable
 *
 * balance es accesor derivado (total − paid), string bcmath escala 2.
 */
final class AccountPayableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'supplier_id'       => $this->supplier_id,
            'purchase_order_id' => $this->purchase_order_id,
            'goods_receipt_id'  => $this->goods_receipt_id,
            'total_amount'      => $this->total_amount,
            'paid_amount'       => $this->paid_amount,
            'balance'           => $this->balance,
            'status'            => $this->status->value,
            'status_label'      => $this->status->label(),
            'due_date'          => $this->due_date?->toDateString(),
            'is_overdue'        => $this->isOverdue(),
            'unblocked_by'      => $this->unblocked_by,
            'supplier'          => new SupplierResource($this->whenLoaded('supplier')),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
