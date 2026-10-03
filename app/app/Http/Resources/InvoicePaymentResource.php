<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoicePayment
 */
final class InvoicePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'invoice_id'     => $this->invoice_id,
            'cash_session_id' => $this->cash_session_id,
            'user_id'        => $this->user_id,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'amount'         => $this->amount,
            'reference'      => $this->reference,
            'paid_at'        => $this->paid_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
