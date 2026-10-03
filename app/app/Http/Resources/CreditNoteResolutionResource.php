<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CreditNoteResolution */
final class CreditNoteResolutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'resolution_type' => $this->resolution_type->value,
            'resolution_label'=> $this->resolution_type->label(),
            'amount'          => (string) $this->amount, // decimal como string (bcmath e2).
            'cash_session_id' => $this->cash_session_id, // Solo en la vía de reembolso en efectivo.
        ];
    }
}
