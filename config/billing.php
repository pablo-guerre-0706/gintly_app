<?php

declare(strict_types=1);

/**
 * Suscripción SaaS de Gintly (cobro de Gintly a cada negocio). ESTRICTAMENTE SEPARADO del módulo comercial
 * del ERP (invoice_payments / receivable_payments), que es el cobro del negocio a SUS clientes.
 *
 * Proveedor único aprobado: Lemon Squeezy (checkout alojado, API REST oficial vía cliente HTTP de Laravel;
 * sin SDK comunitario, sin arquitectura multiproveedor, sin formularios de tarjeta propios).
 *
 * Propósito de despliegue (deployment_purpose) y modo del proveedor (provider_mode) son INDEPENDIENTES de
 * APP_ENV y se gobiernan SOLO por backend (jamás por request/header/rol/endpoint):
 *   - commercial ⇒ exige provider_mode=live (credenciales, catálogo, suscripciones y pagos reales).
 *   - demo       ⇒ exige provider_mode=test (todo sandbox; nunca ingresos reales).
 * Cualquier combinación incoherente debe FALLAR EN CERRADO (sin checkout ni acceso comercial).
 *
 * Precios oficiales del catálogo en NIO (unidades menores enteras = centavos; nunca floats). El cobro
 * EFECTIVO lo realiza Lemon Squeezy en la moneda de su variante (USD); su importe/moneda reales se registran
 * como evidencia desde el webhook. No se usan las tasas FX del ERP de cada tenant para la suscripción SaaS.
 */
return [

    'provider' => 'lemon_squeezy',

    // Gobernados por backend, independientes de APP_ENV. Combinaciones válidas: commercial/live, demo/test.
    'deployment_purpose' => env('BILLING_DEPLOYMENT_PURPOSE', 'demo'),   // commercial | demo
    'provider_mode'      => env('BILLING_PROVIDER_MODE', 'test'),        // live | test

    // Explicit, expiring evaluation access; never enables other tenants or simulates a paid subscription.
    // Requires demo/test AND this opt-in AND an explicit per-business grant.
    'demo_access' => [
        'enabled' => env('BILLING_DEMO_ACCESS_ENABLED', false),
        'business_slug' => env('BILLING_DEMO_BUSINESS_SLUG', ''),
        // Opt-in for several individually granted businesses; never grants access by itself.
        'multiple_businesses' => env('BILLING_DEMO_MULTIPLE_BUSINESSES', false),
        'max_days' => 30,
        // Enrollment window, not a paid subscription. UTC/explicit-offset RFC3339 timestamps.
        'registration' => [
            'enabled' => env('BILLING_EVALUATION_REGISTRATION_ENABLED', false),
            'starts_at' => env('BILLING_EVALUATION_STARTS_AT'),
            'ends_at' => env('BILLING_EVALUATION_ENDS_AT'),
            'days' => env('BILLING_EVALUATION_DAYS', 7),
            'plan' => env('BILLING_EVALUATION_PLAN', 'cadena'),
        ],
    ],

    // Credenciales del proveedor (nunca en código; placeholders en .env.example).
    'api_key'        => env('LEMON_SQUEEZY_API_KEY'),
    'store_id'       => env('LEMON_SQUEEZY_STORE_ID'),
    'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),

    'api_base'     => env('LEMON_SQUEEZY_API_BASE', 'https://api.lemonsqueezy.com/v1'),
    'http_timeout' => (int) env('BILLING_HTTP_TIMEOUT', 15),

    // URLs de retorno/cancelación bajo configuración controlada (no destinos arbitrarios por request).
    'return_url' => env('BILLING_RETURN_URL'),
    'cancel_url' => env('BILLING_CANCEL_URL'),

    // Reintentos/esperas/límites (todos finitos y parametrizables).
    'lock_timeout_seconds' => (int) env('BILLING_LOCK_TIMEOUT', 10),
    'checkout_max_per_minute' => (int) env('BILLING_CHECKOUT_MAX_PER_MINUTE', 10),

    // Vencimiento FINITO del checkout alojado (minutos). Una URL vencida no es utilizable ni bloquea un intento
    // nuevo. Se envía como expires_at al proveedor y se guarda en checkout_intents.
    'checkout_ttl_minutes' => (int) env('BILLING_CHECKOUT_TTL_MINUTES', 60),

    // Tope ACOTADO de páginas al consultar listados del proveedor (checkouts, subscription-invoices). Si hay más
    // páginas que el tope sin hallar lo buscado, la consulta se considera INCOMPLETA (no concluyente).
    'provider_page_cap' => (int) env('BILLING_PROVIDER_PAGE_CAP', 10),

    // Ventana de GRACIA (segundos) tras intentar crear un checkout: mientras no pase, una AUSENCIA en el proveedor
    // no se toma como "no creado" (la creación podría seguir procesándose) → se conserva incierto y se bloquea.
    'checkout_recovery_grace_seconds' => (int) env('BILLING_CHECKOUT_RECOVERY_GRACE', 90),

    // Moneda OFICIAL del catálogo (anunciada/contractual). El cobro efectivo va en la moneda de la variante LS.
    'catalog_currency' => 'NIO',

    /*
     * Catálogo APROBADO (no inventar promos/cupones/trials/instalación ni una 4ª categoría). Importes en
     * centavos NIO. limits.cash_sessions = null ⇒ sin límite comercial. Las features son ACUMULATIVAS
     * (basic ⊂ comercio ⊂ cadena); el resolvedor expande la herencia.
     */
    'catalog' => [
        'basic' => [
            'name'   => 'Plan Inicial',
            'prices' => ['monthly' => ['nio_minor' => 116000], 'annual' => ['nio_minor' => 1392000]],
            'limits' => ['branches' => 1, 'cash_sessions' => 1],
            'features' => ['pos', 'sales', 'catalog', 'inventory', 'cash', 'returns'],
        ],
        'comercio' => [
            'name'   => 'Plan Comercio',
            'prices' => ['monthly' => ['nio_minor' => 228000], 'annual' => ['nio_minor' => 2736000]],
            'limits' => ['branches' => 1, 'cash_sessions' => 3],
            'features' => ['receivables', 'three_way_match', 'anomalies', 'supplier_map'],
        ],
        'cadena' => [
            'name'   => 'Plan Cadena',
            'prices' => ['monthly' => ['nio_minor' => 440000], 'annual' => ['nio_minor' => 5280000]],
            'limits' => ['branches' => 5, 'cash_sessions' => null],
            'features' => ['multi_branch', 'advanced_reports', 'warehouse_transfers'],
        ],
    ],

    'periods' => ['monthly', 'annual'],

    /*
     * Mapa de VARIANTES reales de Lemon Squeezy: 3 planes × 2 periodicidades, SEPARADAS por modo (test/live)
     * para no mezclar identidades de prueba y reales. Solo nombres de variables aquí; los IDs llegan por .env.
     */
    'variants' => [
        'test' => [
            'basic'    => ['monthly' => env('LS_TEST_VARIANT_BASIC_MONTHLY'),    'annual' => env('LS_TEST_VARIANT_BASIC_ANNUAL')],
            'comercio' => ['monthly' => env('LS_TEST_VARIANT_COMERCIO_MONTHLY'), 'annual' => env('LS_TEST_VARIANT_COMERCIO_ANNUAL')],
            'cadena'   => ['monthly' => env('LS_TEST_VARIANT_CADENA_MONTHLY'),   'annual' => env('LS_TEST_VARIANT_CADENA_ANNUAL')],
        ],
        'live' => [
            'basic'    => ['monthly' => env('LS_LIVE_VARIANT_BASIC_MONTHLY'),    'annual' => env('LS_LIVE_VARIANT_BASIC_ANNUAL')],
            'comercio' => ['monthly' => env('LS_LIVE_VARIANT_COMERCIO_MONTHLY'), 'annual' => env('LS_LIVE_VARIANT_COMERCIO_ANNUAL')],
            'cadena'   => ['monthly' => env('LS_LIVE_VARIANT_CADENA_MONTHLY'),   'annual' => env('LS_LIVE_VARIANT_CADENA_ANNUAL')],
        ],
    ],
];
