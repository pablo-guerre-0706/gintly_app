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
            'id' => $this->id,
            'cash_register_id' => $this->cash_register_id,
            'opened_by' => $this->opened_by,
            'closed_by' => $this->closed_by,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Moneda BASE (NIO). Se conservan las claves históricas sin sufijo.
            'opening_amount' => $this->opening_amount,
            'counted_amount' => $this->counted_amount,
            'counted_denominations' => $this->counted_denominations,
            // Arqueo ciego: null mientras 'abierta'; visibles tras el cierre.
            'expected_amount' => $isClosed ? $this->expected_amount : null,
            'difference' => $isClosed ? $this->difference : null,
            // Moneda EXTRANJERA (USD), reconciliada de forma INDEPENDIENTE del NIO.
            'opening_amount_usd' => $this->opening_amount_usd,
            'counted_amount_usd' => $this->counted_amount_usd,
            'counted_denominations_usd' => $this->counted_denominations_usd,
            'expected_amount_usd' => $isClosed ? $this->expected_amount_usd : null,
            'difference_usd' => $isClosed ? $this->difference_usd : null,
            'session_exchange_rate' => $this->session_exchange_rate,
            // Consolidado NIO: INFORMATIVO. Usa la tasa de referencia del cierre y NUNCA sustituye la
            // reconciliación por moneda; si una moneda descuadra, el consolidado lo refleja (no lo oculta).
            'consolidated_nio' => $this->consolidatedNio($isClosed),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closing_notes' => $this->closing_notes,
            'cash_register' => new CashRegisterResource($this->whenLoaded('cashRegister')),
            'movements' => CashMovementResource::collection($this->whenLoaded('movements')),
            'counts' => CashCountResource::collection($this->whenLoaded('counts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Consolidado INFORMATIVO en NIO (solo tras el cierre y con tasa de referencia disponible). Expresa
     * el leg USD en NIO con la tasa snapshot del cierre y lo suma al NIO. No es autoritativo: el estado
     * de la sesión lo decide la reconciliación por moneda.
     *
     * @return array<string, string>|null
     */
    private function consolidatedNio(bool $isClosed): ?array
    {
        $rate = $this->session_exchange_rate;

        if (! $isClosed || $rate === null) {
            return null;
        }

        $expected = bcadd((string) $this->expected_amount, bcmul((string) $this->expected_amount_usd, (string) $rate, 2), 2);
        $counted = bcadd((string) $this->counted_amount, bcmul((string) $this->counted_amount_usd, (string) $rate, 2), 2);

        return [
            'reference_rate' => (string) $rate,
            'expected_amount' => $expected,
            'counted_amount' => $counted,
            'difference' => bcsub($counted, $expected, 2),
        ];
    }
}
