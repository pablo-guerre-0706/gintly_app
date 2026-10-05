<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Geocoding\GeocodeResult;

/**
 * Adaptador de geocodificación intercambiable. Devuelve null cuando no hay proveedor configurado o la
 * dirección no se pudo resolver; nunca lanza hacia el flujo de aprobación (el llamador aísla los errores).
 */
interface Geocoder
{
    public function geocode(int $businessId, string $address): ?GeocodeResult;
}
