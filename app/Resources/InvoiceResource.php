<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 *
 * Todos los montos como string (cast decimal:2), bcmath-safe.
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'folio'           => $this->folio,
            'branch_id'       => $this->branch_id,
            'customer_id'     => $this->customer_id,
            'cash_session_id' => $this->cash_session_id,
            'issued_by'       => $this->issued_by,
            'payment_type'    => $this->payment_type->value,
            'payment_type_label' => $this->payment_type->label(),
            'payment_status'  => $this->payment_status->value,
            'status'          => $this->status->value,
            'status_label'    => $this->status->label(),
            'subtotal'        => $this->subtotal,
            'tax_amount'      => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total'           => $this->total,
            'paid_amount'     => $this->paid_amount,
            'outstanding_balance' => $this->outstandingBalance(),
            'voided_by'       => $this->voided_by,
            'voided_at'       => $this->voided_at?->toIso8601String(),
            'void_reason'     => $this->void_reason,
            'issued_at'       => $this->issued_at?->toIso8601String(),
            'customer'        => new CustomerResource($this->whenLoaded('customer')),
            'sales'           => SaleResource::collection($this->whenLoaded('sales')),
            'payments'        => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
