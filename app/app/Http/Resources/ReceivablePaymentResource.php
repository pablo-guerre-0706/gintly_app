<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReceivablePayment */
final class ReceivablePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'accounts_receivable_id' => $this->accounts_receivable_id,
            'amount'                 => (string) $this->amount,
            'payment_method'         => $this->payment_method->value,
            'reference'              => $this->reference,
            'cash_session_id'        => $this->cash_session_id,
            'invoice_payment_id'     => $this->invoice_payment_id, // asiento fiscal 1:1 (RF-07)
            'paid_at'                => $this->paid_at?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
            // Responsable del cobro (RF-08-06: historial con responsable, fecha, medio).
            'user'                   => new UserResource($this->whenLoaded('user')),
            // Estado de la cuenta tras el abono (balance/status derivados por el motor).
            'account_receivable'     => new AccountReceivableResource($this->whenLoaded('accountReceivable')),
        ];
    }
}
