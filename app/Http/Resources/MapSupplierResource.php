<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 *
 * MOD-04 · Proveedor para el MAPA: solo aprobado+activo y con ubicaciones CONFIRMADAS (las carga el
 * controlador ya filtradas). Expone únicamente lo necesario para pintar el marcador.
 */
final class MapSupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'status'    => $this->status->value,
            'locations' => $this->locations->map(fn ($location) => [
                'id'         => $location->id,
                'address'    => $location->address,
                'latitude'   => $location->latitude,
                'longitude'  => $location->longitude,
                'is_primary' => $location->is_primary,
                'quality'    => $location->quality,
                'confirmed_at' => $location->confirmed_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
