<?php

declare(strict_types=1);

namespace App\Support\Geocoding;

use App\Contracts\Geocoder;

/**
 * Driver determinista (sin red): resuelve direcciones contra un mapa en memoria. Útil para pruebas y
 * semillas. La clave es la dirección normalizada (trim + minúsculas); el valor, las coordenadas y metadatos.
 */
final class ArrayGeocoder implements Geocoder
{
    /** @param array<string, array{latitude: string, longitude: string, quality?: string, external_id?: string}> $results */
    public function __construct(private readonly array $results) {}

    public function geocode(int $businessId, string $address): ?GeocodeResult
    {
        $key = mb_strtolower(trim($address));

        foreach ($this->results as $candidate => $data) {
            if (mb_strtolower(trim((string) $candidate)) === $key) {
                return new GeocodeResult(
                    latitude: (string) $data['latitude'],
                    longitude: (string) $data['longitude'],
                    quality: $data['quality'] ?? null,
                    externalId: $data['external_id'] ?? null,
                );
            }
        }

        return null;
    }
}
