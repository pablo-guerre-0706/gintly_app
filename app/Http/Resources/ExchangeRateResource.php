<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExchangeRate
 *
 * MOD-06 · Vigencia de tipo de cambio (historial inmutable). Expone la tasa nativa, la vigencia y el
 * responsable (no-repudio). `rate` se serializa como string con 6 decimales (nunca float).
 */
final class ExchangeRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'currency'       => $this->currency->value,
            'rate'           => $this->rate,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'created_by'     => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
