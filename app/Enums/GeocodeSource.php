<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MOD-04 · Procedencia de las coordenadas de una ubicación de proveedor. `manual`: fijadas/corregidas por
 * un usuario; `geocoded`: resueltas por el adaptador de geocodificación; `external`: provenientes de un
 * descubrimiento externo (mapa/import) aún sin confirmar.
 */
enum GeocodeSource: string
{
    case Manual   = 'manual';
    case Geocoded = 'geocoded';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Manual   => 'Manual',
            self::Geocoded => 'Geocodificada',
            self::External => 'Externa',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
