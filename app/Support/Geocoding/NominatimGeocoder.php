<?php

declare(strict_types=1);

namespace App\Support\Geocoding;

use App\Contracts\Geocoder;
use Illuminate\Support\Facades\Http;

/**
 * SOLO DESARROLLO. Nominatim público NO es apto para producción (política de uso, sin SLA ni soporte de
 * volumen). Se incluye como ejemplo de adaptador HTTP intercambiable. No usa ni expone claves de API.
 * Cualquier error de red devuelve null (la ubicación queda pendiente; nunca afecta la aprobación).
 */
final class NominatimGeocoder implements Geocoder
{
    /** @param array{base_uri?: string, timeout?: int} $config */
    public function __construct(private readonly array $config) {}

    public function geocode(int $businessId, string $address): ?GeocodeResult
    {
        $base = rtrim((string) ($this->config['base_uri'] ?? 'https://nominatim.openstreetmap.org'), '/');
        $timeout = (int) ($this->config['timeout'] ?? 5);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'Gintly/1.0 (geocoder)'])
                ->get($base.'/search', ['q' => $address, 'format' => 'jsonv2', 'limit' => 1]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $first = $response->json()[0] ?? null;
        if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
            return null;
        }

        return new GeocodeResult(
            latitude: (string) $first['lat'],
            longitude: (string) $first['lon'],
            quality: isset($first['type']) ? (string) $first['type'] : null,
            externalId: isset($first['place_id']) ? (string) $first['place_id'] : null,
        );
    }
}
