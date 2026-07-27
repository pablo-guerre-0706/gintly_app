<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashSession
 *
 * D-21 / H-49 · ARQUEO CIEGO. expected_amount y difference se OCULTAN mientras
 * la sesión esté abierta: exponerlos rompería el arqueo ciego (el cajero vería
 * el esperado antes de contar). Solo se revelan tras el cierre. Es la razón de
 * ser de este Resource; no es un detalle cosmético.
 */
final class CashSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isClosed = $this->status->isClosed();

        return [
            'id'                    => $this->id,
            'cash_register_id'      => $this->cash_register_id,
            'opened_by'             => $this->opened_by,
            'closed_by'             => $this->closed_by,
            'status'                => $this->status->value,
            'status_label'          => $this->status->label(),
            'opening_amount'        => $this->opening_amount,
            'counted_amount'        => $this->counted_amount,
            'counted_denominations' => $this->counted_denominations,
            // Arqueo ciego: null mientras 'abierta'; visibles tras el cierre.
            'expected_amount'       => $isClosed ? $this->expected_amount : null,
            'difference'            => $isClosed ? $this->difference : null,
            'opened_at'             => $this->opened_at?->toIso8601String(),
            'closed_at'             => $this->closed_at?->toIso8601String(),
            'closing_notes'         => $this->closing_notes,
            'cash_register'         => new CashRegisterResource($this->whenLoaded('cashRegister')),
            'movements'             => CashMovementResource::collection($this->whenLoaded('movements')),
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}
