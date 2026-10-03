<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Dispatch */
final class DispatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'status'        => $this->status->value,
            'status_label'  => $this->status->label(),
            'invoice_id'    => $this->invoice_id,
            'branch_id'     => $this->branch_id,
            'warehouse_id'  => $this->warehouse_id,
            'received_by'   => $this->received_by,
            'notes'         => $this->notes,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'reverted_by'   => $this->reverted_by,
            'reverted_at'   => $this->reverted_at?->toIso8601String(),
            'revert_reason' => $this->revert_reason,

            // Relaciones opcionales (solo si se cargaron): sin N+1.
            'user'        => new UserResource($this->whenLoaded('user')),        // Responsable del retiro.
            'reverted_by_user' => new UserResource($this->whenLoaded('revertedBy')),
            'invoice'     => new InvoiceResource($this->whenLoaded('invoice')),
            'warehouse'   => new WarehouseResource($this->whenLoaded('warehouse')),
            'items'       => DispatchItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
