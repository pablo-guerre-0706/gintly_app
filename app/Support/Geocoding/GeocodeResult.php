<?php

declare(strict_types=1);

namespace App\Support\Geocoding;

/**
 * Resultado inmutable de una geocodificación. Coordenadas como strings decimales (escala del modelo),
 * calidad reportada por el proveedor e identificador externo opcional. NUNCA transporta claves de API.
 */
final class GeocodeResult
{
    public function __construct(
        public readonly string $latitude,
        public readonly string $longitude,
        public readonly ?string $quality = null,
        public readonly ?string $externalId = null,
    ) {}
}
