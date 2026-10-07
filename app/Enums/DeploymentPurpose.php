<?php

declare(strict_types=1);

namespace App\Enums;

/** Propósito del despliegue (gobernado SOLO por backend, independiente de APP_ENV). */
enum DeploymentPurpose: string
{
    case Commercial = 'commercial'; // exige provider_mode=live
    case Demo       = 'demo';       // exige provider_mode=test

    public function requiredProviderMode(): ProviderMode
    {
        return $this === self::Commercial ? ProviderMode::Live : ProviderMode::Test;
    }
}
