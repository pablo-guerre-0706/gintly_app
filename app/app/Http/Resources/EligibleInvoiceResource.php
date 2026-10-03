<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 *
 * Vista MÍNIMA para seleccionar una factura devolvible. Solo identificación: no expone importes,
 * datos fiscales ni administrativos (el bodeguero no tiene facturas.ver).
 */
final class EligibleInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_id' => $this->id,
            'folio' => $this->folio,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'issued_at' => $this->issued_at?->toIso8601String(),
        ];
    }
}
