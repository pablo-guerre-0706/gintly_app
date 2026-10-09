<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Business;
use App\Models\DemoAccessGrant;
use App\Models\PlanSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Lectura del estado comercial para la compuerta de acceso, las capacidades y los límites. Separa suscripción
 * comercial de la suspensión administrativa del negocio y de la autorización por rol/perfil/sucursal.
 *
 * El acceso por suscripción exige, ADEMÁS de una vigencia pagada, COHERENCIA de despliegue (propósito/modo) y CORRESPONDENCIA de
 * la suscripción con el proveedor, modo y tienda CONFIGURADOS: una suscripción TEST no habilita un despliegue
 * commercial/live (ni al revés), ni una de otra tienda. Alternativamente, una concesión explícita, temporal y
 * revocable permite evaluar negocios explícitamente concedidos en demo/test; nunca representa pago ni suscripción.
 */
final class CommercialAccess
{
    public function __construct(private readonly BillingMode $mode)
    {
    }

    /** Suscripción (una por negocio) sin BusinessScope. Resiliente: si la tabla no es consultable, null. */
    public function subscriptionFor(int $businessId): ?PlanSubscription
    {
        try {
            return PlanSubscription::query()->where('business_id', $businessId)->first();
        } catch (QueryException $e) {
            report($e);

            return null; // entorno sin tabla (p. ej. smoke test en memoria): fail-closed, sin acceso.
        }
    }

    /** Paid access OR an explicit, current evaluation grant; role/branch/tenant checks remain independent. */
    public function grantsAccess(int $businessId, ?Carbon $now = null): bool
    {
        return $this->activePlanKey($businessId, $now) !== null;
    }

    /** Plan vigente que gobierna capacidades/límites, o null si no hay acceso (incluida la incoherencia de modo/tienda). */
    public function activePlanKey(int $businessId, ?Carbon $now = null): ?string
    {
        $sub = $this->subscriptionFor($businessId);

        if ($sub !== null && $this->matchesConfiguredProvider($sub) && $sub->grantsAccessNow($now)) {
            return (string) $sub->plan_key;
        }

        return $this->demoGrantFor($businessId, $now)?->plan_key;
    }

    /** Selection guard shared by administrative grants and registration enrollment. */
    public function canGrantDemoTo(Business $business): bool
    {
        if (config('billing.demo_access.enabled') !== true || ! $business->status?->canOperate()
            || (config('billing.demo_access.multiple_businesses') !== true
                && ((string) config('billing.demo_access.business_slug', '') === ''
                    || $business->slug !== (string) config('billing.demo_access.business_slug')))) {
            return false;
        }
        try {
            return $this->mode->isDemo() && $this->mode->activeMode()->value === 'test';
        } catch (\App\Exceptions\BillingUnavailableException) {
            return false;
        }
    }

    /** Explicit per-business entitlement; enrollment closing never extends or revokes existing grants. */
    public function demoGrantFor(int $businessId, ?Carbon $now = null): ?DemoAccessGrant
    {
        if (config('billing.demo_access.enabled') !== true) {
            return null;
        }

        try {
            $business = Business::query()->whereKey($businessId)->first();
            if ($business === null || ! $this->canGrantDemoTo($business)) {
                return null;
            }
            $grant = DemoAccessGrant::query()->where('business_id', $businessId)->first();

            return $grant !== null && $grant->isCurrent($now)
                && array_key_exists($grant->plan_key, (array) config('billing.catalog', [])) ? $grant : null;
        } catch (QueryException $error) {
            report($error);

            return null;
        } catch (\App\Exceptions\BillingUnavailableException) {
            return null;
        }
    }

    /** A real paid subscription always takes precedence over a demonstration. */
    public function hasPaidAccess(int $businessId, ?Carbon $now = null): bool
    {
        $sub = $this->subscriptionFor($businessId);

        return $sub !== null && $this->matchesConfiguredProvider($sub) && $sub->grantsAccessNow($now);
    }

    /**
     * La suscripción corresponde al despliegue CONFIGURADO: propósito/modo coherentes (commercial/live o demo/test),
     * mismo proveedor, mismo modo del proveedor (un pago TEST NO habilita commercial/live) y misma tienda cuando hay
     * una configurada. Cualquier discrepancia ⇒ no concede acceso.
     */
    private function matchesConfiguredProvider(PlanSubscription $sub): bool
    {
        try {
            $mode = $this->mode->activeMode()->value; // lanza si propósito/modo son incoherentes
        } catch (\Throwable $e) {
            return false; // incoherente/incompleto ⇒ sin acceso comercial (fail-closed)
        }

        if ((string) $sub->provider !== (string) config('billing.provider', 'lemon_squeezy')) {
            return false;
        }
        if ((string) $sub->provider_mode !== $mode) {
            return false; // suscripción de otro modo (p. ej. TEST en un despliegue live)
        }

        $store = (string) config('billing.store_id');
        if ($store !== '' && (string) $sub->store_id !== $store) {
            return false; // de otra tienda (cuando hay tienda configurada)
        }

        return true;
    }
}
