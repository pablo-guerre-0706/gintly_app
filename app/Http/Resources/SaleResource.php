<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
final class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'branch_id'       => $this->branch_id,
            'customer_id'     => $this->customer_id,
            'user_id'         => $this->user_id,
            'status'          => $this->status->value,
            'status_label'    => $this->status->label(),
            'table_reference' => $this->table_reference,
            'subtotal'        => $this->subtotal,
            'notes'           => $this->notes,
            'opened_at'       => $this->opened_at?->toIso8601String(),
            'confirmed_at'    => $this->confirmed_at?->toIso8601String(),
            'customer'        => new CustomerResource($this->whenLoaded('customer')),
            'items'           => SaleItemResource::collection($this->whenLoaded('items')),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}

