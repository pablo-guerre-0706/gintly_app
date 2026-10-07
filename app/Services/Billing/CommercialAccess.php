<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\PlanSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Lectura del estado comercial para la compuerta de acceso, las capacidades y los límites. Separa suscripción
 * comercial de la suspensión administrativa del negocio y de la autorización por rol/perfil/sucursal.
 *
 * El acceso exige, ADEMÁS de una vigencia pagada, COHERENCIA de despliegue (propósito/modo) y CORRESPONDENCIA de
 * la suscripción con el proveedor, modo y tienda CONFIGURADOS: una suscripción TEST no habilita un despliegue
 * commercial/live (ni al revés), ni una de otra tienda.
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

    /** ¿El negocio puede operar los módulos del ERP ahora? Vigencia + coherencia de modo + correspondencia del proveedor. */
    public function grantsAccess(int $businessId, ?Carbon $now = null): bool
    {
        $sub = $this->subscriptionFor($businessId);

        return $sub !== null && $this->matchesConfiguredProvider($sub) && $sub->grantsAccessNow($now);
    }

    /** Plan vigente que gobierna capacidades/límites, o null si no hay acceso (incluida la incoherencia de modo/tienda). */
    public function activePlanKey(int $businessId, ?Carbon $now = null): ?string
    {
        $sub = $this->subscriptionFor($businessId);

        return ($sub !== null && $this->matchesConfiguredProvider($sub) && $sub->grantsAccessNow($now))
            ? (string) $sub->plan_key
            : null;
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
