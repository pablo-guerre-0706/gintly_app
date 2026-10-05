<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Geocoder;
use App\Enums\GeocodeSource;
use App\Models\Supplier;
use App\Models\SupplierLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * MOD-04 · Geocodificación de ubicaciones de proveedor, DESACOPLADA de la aprobación. Un fallo del
 * proveedor (o su ausencia: driver null) deja la ubicación pendiente SIN revertir la aprobación del
 * proveedor. La confirmación sigue siendo un acto aparte (manual o explícito), nunca automático.
 */
final class SupplierGeocodingService
{
    public function __construct(private readonly Geocoder $geocoder) {}

    /**
     * Intenta geocodificar UNA ubicación. Devuelve true si obtuvo coordenadas. Aísla cualquier error del
     * proveedor (devuelve false): nunca propaga la excepción al flujo que la invocó. NO confirma la ubicación.
     */
    public function geocodificar(SupplierLocation $location): bool
    {
        try {
            $result = $this->geocoder->geocode((int) $location->business_id, (string) $location->address);
        } catch (\Throwable $e) {
            Log::warning('Geocodificación fallida para supplier_location '.$location->id.': '.$e->getMessage());

            return false;
        }

        if ($result === null) {
            return false; // sin proveedor o sin resultado: la ubicación queda pendiente.
        }

        $location->latitude       = $result->latitude;
        $location->longitude      = $result->longitude;
        $location->geocode_source = GeocodeSource::Geocoded->value;
        $location->quality        = $result->quality;
        $location->geocoded_at    = Carbon::now();
        if ($result->externalId !== null && $location->external_id === null) {
            $location->external_id = $result->externalId;
        }
        $location->save();

        return true;
    }

    /**
     * Tras la aprobación: intenta geocodificar las ubicaciones del proveedor que aún no tienen coordenadas.
     * Totalmente aislado (no lanza): el proveedor permanece aprobado pase lo que pase con la geocodificación.
     */
    public function geocodificarPendientesDe(Supplier $supplier): void
    {
        $pendientes = SupplierLocation::query()
            ->where('supplier_id', $supplier->id)
            ->whereNull('latitude')
            ->get();

        foreach ($pendientes as $location) {
            try {
                $this->geocodificar($location);
            } catch (\Throwable $e) {
                Log::warning('Geocodificación post-aprobación fallida: '.$e->getMessage());
            }
        }
    }
}
