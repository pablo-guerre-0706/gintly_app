<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Business */
final class BusinessResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'plan'       => $this->plan,
            'status'     => $this->status?->value,   // BusinessStatus (backed enum) -> string
            // Tasa estándar VIGENTE resuelta desde tax_rules (fuente operativa única);
            // la columna es solo un espejo de compatibilidad. Devuelve NULL cuando el
            // negocio no tiene regla estándar (no hay presunción de 15 %). String bcmath.
            'tax_rate'   => $this->standardTaxRate()
                ?? ($this->tax_rate !== null ? (string) $this->tax_rate : null),
            'timezone'   => $this->timezone,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
