<?php

declare(strict_types=1);

namespace App\Support\Geocoding;

use App\Contracts\Geocoder;

/**
 * Sin proveedor configurado: nunca geocodifica. La ubicación permanece pendiente; la aprobación del
 * proveedor nunca se revierte por esto. Es el driver por defecto (producción sin proveedor).
 */
final class NullGeocoder implements Geocoder
{
    public function geocode(int $businessId, string $address): ?GeocodeResult
    {
        return null;
    }
}
