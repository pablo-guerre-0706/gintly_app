<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
final class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'supplier_id'    => $this->supplier_id,
            'branch_id'      => $this->branch_id,
            'user_id'        => $this->user_id,
            'status'         => $this->status->value,
            'status_label'   => $this->status->label(),
            'expected_total' => $this->expected_total,
            'ordered_at'     => $this->ordered_at?->toDateString(),
            'notes'          => $this->notes,
            'supplier'       => new SupplierResource($this->whenLoaded('supplier')),
            'items'          => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
