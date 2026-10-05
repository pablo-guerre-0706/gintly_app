<?php

declare(strict_types=1);

namespace App\Support\Geocoding;

use App\Contracts\Geocoder;
use Illuminate\Support\Facades\Cache;

/**
 * Decorador que cachea los ACIERTOS de geocodificación por (negocio, dirección) durante un TTL. Los
 * fallos (null) no se cachean: se reintentan en la próxima solicitud. Evita golpear al proveedor externo
 * para la misma dirección repetidamente.
 */
final class CachingGeocoder implements Geocoder
{
    public function __construct(
        private readonly Geocoder $inner,
        private readonly int $ttlSeconds,
    ) {}

    public function geocode(int $businessId, string $address): ?GeocodeResult
    {
        $key = 'geocode:'.$businessId.':'.sha1(mb_strtolower(trim($address)));

        $cached = Cache::get($key);
        if ($cached instanceof GeocodeResult) {
            return $cached;
        }

        $result = $this->inner->geocode($businessId, $address);

        if ($result instanceof GeocodeResult) {
            Cache::put($key, $result, $this->ttlSeconds);
        }

        return $result;
    }
}
