<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashCount
 *
 * Arqueo ciego YA registrado: a diferencia de la sesión abierta, un arqueo es un conteo COMPLETADO,
 * por lo que REVELA expected_amount y difference (el cajero ya contó; la ceguera aplica ANTES de
 * registrar). Evidencia inmutable.
 */
final class CashCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cash_session_id' => $this->cash_session_id,
            'user_id' => $this->user_id,
            'counted_amount' => $this->counted_amount,
            'expected_amount' => $this->expected_amount,
            'difference' => $this->difference,
            'counted_denominations' => $this->counted_denominations,
            // Leg USD (doble moneda): reconciliación independiente del NIO.
            'counted_amount_usd' => $this->counted_amount_usd,
            'expected_amount_usd' => $this->expected_amount_usd,
            'difference_usd' => $this->difference_usd,
            'counted_denominations_usd' => $this->counted_denominations_usd,
            'counted_at' => $this->counted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
