<?php

declare(strict_types=1);

/**
 * MOD-04 · Geocodificación de direcciones de proveedor. El adaptador es INTERCAMBIABLE y CONFIGURABLE:
 * el driver por defecto es `null` (sin proveedor configurado → no se geocodifica, la ubicación queda
 * pendiente, jamás se revierte una aprobación). `array` es un driver determinista para pruebas/semillas.
 * `nominatim` es solo para desarrollo (NUNCA producción) y no expone claves. Los resultados se cachean.
 */
return [

    'driver' => env('GEOCODER_DRIVER', 'null'),

    // TTL de la caché de geocodificación (segundos).
    'cache_ttl' => (int) env('GEOCODER_CACHE_TTL', 86400),

    'drivers' => [

        'null' => [],

        // Mapa dirección → coordenadas, inyectable en pruebas/semillas (determinista, sin red).
        'array' => [
            'results' => [],
        ],

        // SOLO desarrollo. Nominatim público no es apto para producción (política de uso + sin SLA).
        'nominatim' => [
            'base_uri' => env('GEOCODER_NOMINATIM_URI', 'https://nominatim.openstreetmap.org'),
            'timeout'  => (int) env('GEOCODER_NOMINATIM_TIMEOUT', 5),
        ],
    ],
];
