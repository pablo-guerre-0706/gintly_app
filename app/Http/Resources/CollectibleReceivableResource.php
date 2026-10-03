<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AccountReceivable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 7 · Vista MÍNIMA de una CxC cobrable para la operación de cobro (cajero). No expone el
 * detalle administrativo completo de la cartera; solo lo necesario para localizar y cobrar.
 *
 * @mixin AccountReceivable
 */
final class CollectibleReceivableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'customer_id'    => $this->customer_id,
            'customer_name'  => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'invoice_id'     => $this->invoice_id,
            'invoice_folio'  => $this->whenLoaded('invoice', fn () => $this->invoice?->folio),
            'branch_id'      => $this->whenLoaded('invoice', fn () => $this->invoice?->branch_id),
            'total_amount'   => (string) $this->total_amount,
            'paid_amount'    => (string) $this->paid_amount,
            'balance'        => (string) $this->balance,
            'status'         => $this->status instanceof \App\Enums\AccountReceivableStatus ? $this->status->value : (string) $this->status,
            'due_date'       => $this->due_date?->toDateString(),
        ];
    }
}
