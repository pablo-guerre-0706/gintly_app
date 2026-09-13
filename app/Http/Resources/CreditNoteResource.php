<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CreditNote */
final class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'folio'                 => $this->folio,
            'invoice_id'            => $this->invoice_id,
            'sales_return_id'       => $this->sales_return_id,
            'customer_id'           => $this->customer_id,
            'cash_session_id'       => $this->cash_session_id,
            'resolution_type'       => $this->resolution_type->value,
            'resolution_label'      => $this->resolution_type->label(),
            'total_amount'          => (string) $this->total_amount,
            'tax_amount'            => (string) $this->tax_amount,
            'status'                => $this->status->value,
            'status_label'          => $this->status->label(),
            'issued_at'             => $this->issued_at?->toIso8601String(),
            'issued_by'             => $this->issued_by,
        ];
    }
}
