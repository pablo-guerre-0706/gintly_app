<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SalesReturn */
final class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'status'         => $this->status->value,
            'status_label'   => $this->status->label(),
            'invoice_id'     => $this->invoice_id,
            'customer_id'    => $this->customer_id,
            'branch_id'      => $this->branch_id,
            'total_returned' => (string) $this->total_returned,
            'returned_at'    => $this->returned_at?->toIso8601String(),
            'notes'          => $this->notes,

            'user'        => new UserResource($this->whenLoaded('user')),
            'items'       => SalesReturnItemResource::collection($this->whenLoaded('items')),
            'credit_note' => new CreditNoteResource($this->whenLoaded('creditNote')),
        ];
    }
}
