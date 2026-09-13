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

            'user'  => new UserResource($this->whenLoaded('user')),
            'items' => DispatchItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
