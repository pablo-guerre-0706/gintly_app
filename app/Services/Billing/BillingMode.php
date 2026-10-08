<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\DeploymentPurpose;
use App\Enums\ProviderMode;
use App\Exceptions\BillingUnavailableException;

/**
 * Propósito de despliegue (commercial/demo) y modo del proveedor (live/test), gobernados SOLO por backend e
 * independientes de APP_ENV. Combinaciones válidas: commercial/live y demo/test. Cualquier otra, o un valor
 * ausente/ inválido, FALLA EN CERRADO (BillingUnavailable sanitizado): sin checkout ni acceso comercial.
 */
final class BillingMode
{
    public function purpose(): DeploymentPurpose
    {
        $value = DeploymentPurpose::tryFrom((string) config('billing.deployment_purpose'));

        if ($value === null) {
            throw new BillingUnavailableException();
        }

        return $value;
    }

    public function providerMode(): ProviderMode
    {
        $value = ProviderMode::tryFrom((string) config('billing.provider_mode'));

        if ($value === null) {
            throw new BillingUnavailableException();
        }

        return $value;
    }

    /** Verifica coherencia; lanza si demo/live, commercial/test o valores inválidos. */
    public function assertCoherent(): void
    {
        if ($this->purpose()->requiredProviderMode() !== $this->providerMode()) {
            throw new BillingUnavailableException();
        }
    }

    /** Modo activo tras verificar coherencia. Úsalo para resolver variantes y para restringir recursos. */
    public function activeMode(): ProviderMode
    {
        $this->assertCoherent();

        return $this->providerMode();
    }

    public function isDemo(): bool
    {
        return $this->purpose() === DeploymentPurpose::Demo;
    }
}
