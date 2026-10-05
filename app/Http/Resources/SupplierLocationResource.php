<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SupplierLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierLocation
 *
 * MOD-04 · Ubicación de proveedor. `confirmed` indica si entra al mapa. No expone claves ni datos del
 * proveedor de geocodificación más allá de la calidad reportada.
 */
final class SupplierLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'supplier_id'    => $this->supplier_id,
            'address'        => $this->address,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'geocode_source' => $this->geocode_source?->value,
            'external_id'    => $this->external_id,
            'quality'        => $this->quality,
            'is_primary'     => $this->is_primary,
            'confirmed'      => $this->isConfirmed(),
            'geocoded_at'    => $this->geocoded_at?->toIso8601String(),
            'confirmed_at'   => $this->confirmed_at?->toIso8601String(),
            'confirmed_by'   => $this->confirmed_by,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
