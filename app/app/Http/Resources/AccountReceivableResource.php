<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AccountReceivable */
final class AccountReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'customer_id'  => $this->customer_id,
            'invoice_id'   => $this->invoice_id,
            'total_amount' => (string) $this->total_amount,
            'paid_amount'  => (string) $this->paid_amount,
            'balance'      => (string) $this->balance, // Derivado por el motor.
            'status'       => $this->status->value,
            'status_label' => $this->status->label(),
            'due_date'     => $this->due_date?->toDateString(),
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),

            // Relaciones opcionales (solo si se cargaron).
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'invoice'  => new InvoiceResource($this->whenLoaded('invoice')),
            'payments' => ReceivablePaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
