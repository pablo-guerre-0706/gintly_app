<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\GeocodeSource;
use App\Models\Supplier;
use App\Models\SupplierLocation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOD-04 · Ciclo de vida de las ubicaciones de proveedor. Invariantes de dominio (no solo del FormRequest):
 * una sola principal por proveedor (candado de motor traducido a 422), confirmación manual que exige
 * coordenadas, e INVALIDACIÓN de la confirmación al cambiar la dirección (las coordenadas viejas dejan de
 * ser válidas → la ubicación sale del mapa hasta reconfirmar/regeocodificar).
 */
final class SupplierLocationService
{
    /** @param array{address: string, latitude?: ?string, longitude?: ?string, external_id?: ?string, is_primary?: bool, geocode_source?: ?string} $data */
    public function crear(Supplier $supplier, array $data): SupplierLocation
    {
        return DB::transaction(function () use ($supplier, $data): SupplierLocation {
            $hasCoords = isset($data['latitude'], $data['longitude']) && $data['latitude'] !== null && $data['longitude'] !== null;

            $location = new SupplierLocation([
                'supplier_id'    => $supplier->id,
                'address'        => $data['address'],
                'latitude'       => $hasCoords ? (string) $data['latitude'] : null,
                'longitude'      => $hasCoords ? (string) $data['longitude'] : null,
                // Coordenadas aportadas al alta (descubrimiento externo del mapa) ⇒ 'external', aún sin confirmar.
                'geocode_source' => $hasCoords ? GeocodeSource::External->value : null,
                'external_id'    => $data['external_id'] ?? null,
                'is_primary'     => (bool) ($data['is_primary'] ?? false),
            ]);

            return $this->persist($location);
        });
    }

    /** @param array{address?: string, is_primary?: bool} $data */
    public function actualizar(User $actor, SupplierLocation $location, array $data): SupplierLocation
    {
        return DB::transaction(function () use ($location, $data): SupplierLocation {
            $location = SupplierLocation::query()->whereKey($location->getKey())->lockForUpdate()->firstOrFail();

            if (array_key_exists('address', $data) && trim((string) $data['address']) !== (string) $location->address) {
                // Cambió la dirección: las coordenadas y la confirmación anteriores dejan de ser válidas.
                $location->address        = trim((string) $data['address']);
                $location->latitude       = null;
                $location->longitude      = null;
                $location->geocode_source = null;
                $location->quality        = null;
                $location->geocoded_at    = null;
                $location->confirmed_at   = null;
                $location->confirmed_by   = null;
            }

            if (array_key_exists('is_primary', $data)) {
                $location->is_primary = (bool) $data['is_primary'];
            }

            return $this->persist($location);
        });
    }

    /**
     * Confirmación/corrección MANUAL del marcador (ROL-01/ROL-02). Si se envían coordenadas, se fijan
     * (procedencia 'manual'); en cualquier caso debe haber coordenadas para confirmar. Marca confirmed_at/by.
     *
     * @param array{latitude?: ?string, longitude?: ?string} $coords
     */
    public function confirmar(User $actor, SupplierLocation $location, array $coords): SupplierLocation
    {
        return DB::transaction(function () use ($actor, $location, $coords): SupplierLocation {
            $location = SupplierLocation::query()->whereKey($location->getKey())->lockForUpdate()->firstOrFail();

            $lat = $coords['latitude'] ?? null;
            $lng = $coords['longitude'] ?? null;

            if ($lat !== null && $lng !== null) {
                $location->latitude       = (string) $lat;
                $location->longitude      = (string) $lng;
                $location->geocode_source = GeocodeSource::Manual->value;
            }

            if ($location->latitude === null || $location->longitude === null) {
                throw ValidationException::withMessages([
                    'latitude' => ['No se puede confirmar una ubicación sin coordenadas; geocodifique o indique lat/lng.'],
                ]);
            }

            $location->confirmed_at = Carbon::now();
            $location->confirmed_by = $actor->id;

            return $this->persist($location);
        });
    }

    private function persist(SupplierLocation $location): SupplierLocation
    {
        try {
            $location->save();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'uniq_supplier_primary_location')) {
                throw ValidationException::withMessages([
                    'is_primary' => ['El proveedor ya tiene una ubicación principal.'],
                ]);
            }
            throw $e;
        }

        return $location->refresh();
    }
}
