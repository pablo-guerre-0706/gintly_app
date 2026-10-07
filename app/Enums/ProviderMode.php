<?php

declare(strict_types=1);

namespace App\Enums;

/** Modo del proveedor de pagos. live = recursos y cobros reales; test = sandbox. */
enum ProviderMode: string
{
    case Live = 'live';
    case Test = 'test';
}
