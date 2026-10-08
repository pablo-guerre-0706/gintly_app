<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Exceptions\BillingUnavailableException;

/**
 * Catálogo APROBADO (config/billing.php). Resuelve plan+periodicidad → precio NIO (centavos), límites,
 * capacidades acumulativas y variante real de Lemon Squeezy del modo activo. El backend es la ÚNICA fuente
 * de precio/moneda/tienda/variante; el cliente nunca los aporta. Las capacidades heredan basic ⊂ comercio ⊂
 * cadena. Un desajuste de variante/modo impide crear un checkout cobrable.
 */
final class BillingCatalog
{
    /** Orden de herencia de capacidades. */
    private const INHERITANCE = ['basic', 'comercio', 'cadena'];

    public function __construct(private readonly BillingMode $mode)
    {
    }

    /** @return array<int, string> claves de plan públicas, en orden. */
    public function planKeys(): array
    {
        return array_keys((array) config('billing.catalog', []));
    }

    public function hasPlan(string $plan): bool
    {
        return array_key_exists($plan, (array) config('billing.catalog', []));
    }

    public function hasPeriod(string $period): bool
    {
        return in_array($period, (array) config('billing.periods', []), true);
    }

    /** @return array<string, mixed> definición cruda del plan. */
    public function plan(string $plan): array
    {
        $def = (array) config("billing.catalog.{$plan}");

        if ($def === []) {
            throw new BillingUnavailableException();
        }

        return $def;
    }

    /** Precio ANUNCIADO en centavos NIO para plan+periodicidad. */
    public function catalogMinor(string $plan, BillingPeriod $period): int
    {
        $def = $this->plan($plan);
        $minor = $def['prices'][$period->value]['nio_minor'] ?? null;

        if (! is_int($minor)) {
            throw new BillingUnavailableException();
        }

        return $minor;
    }

    /** Capacidades EFECTIVAS del plan (acumulativas por herencia). @return array<int, string> */
    public function features(string $plan): array
    {
        $this->plan($plan); // valida

        $features = [];
        foreach (self::INHERITANCE as $tier) {
            $features = array_merge($features, (array) config("billing.catalog.{$tier}.features", []));
            if ($tier === $plan) {
                break;
            }
        }

        return array_values(array_unique($features));
    }

    public function planHasFeature(string $plan, string $feature): bool
    {
        return in_array($feature, $this->features($plan), true);
    }

    /** Rango del plan en la jerarquía acumulativa (basic<comercio<cadena). -1 si no existe. Compara ascenso/descenso. */
    public function tierRank(string $plan): int
    {
        $index = array_search($plan, self::INHERITANCE, true);

        return $index === false ? -1 : (int) $index;
    }

    /** Límite del plan; null ⇒ sin límite comercial. */
    public function limit(string $plan, string $key): ?int
    {
        $value = $this->plan($plan)['limits'][$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * Variante REAL de Lemon Squeezy para plan+periodicidad en el MODO ACTIVO (coherente). Ausencia ⇒
     * configuración incompleta ⇒ no se puede crear un checkout cobrable (BillingUnavailable).
     */
    public function variantId(string $plan, BillingPeriod $period): string
    {
        $mode = $this->mode->activeMode()->value;
        $variant = config("billing.variants.{$mode}.{$plan}.{$period->value}");

        if (! is_string($variant) || $variant === '') {
            throw new BillingUnavailableException();
        }

        return $variant;
    }

    public function storeId(): string
    {
        $store = (string) config('billing.store_id');

        if ($store === '') {
            throw new BillingUnavailableException();
        }

        return $store;
    }
}
