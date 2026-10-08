<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Business;
use App\Models\PlanSubscription;
use Illuminate\Support\Carbon;

/**
 * Colaborador de prueba LIMPIO: representa un pago VERIFICADO sembrando una suscripción comercial activa con
 * vigencia futura para un negocio de prueba. NO es un bypass del middleware ni una suscripción ficticia sobre
 * datos reales: es fixture de prueba (revertido por la transacción del test). Por defecto 'cadena' para que
 * los flujos operativos existentes (multisucursal, varias cajas) no choquen con capacidades/límites.
 */
trait ActivatesBusinessSubscription
{
    protected function activateSubscription(
        Business|int $business,
        string $plan = 'cadena',
        string $period = 'annual',
        ?Carbon $paidUntil = null,
    ): PlanSubscription {
        $businessId = $business instanceof Business ? (int) $business->id : $business;

        $sub = new PlanSubscription();
        $sub->forceFill([
            'business_id'          => $businessId,
            'plan_key'             => $plan,
            'period'               => $period,
            'status'               => 'active',
            'provider'             => 'lemon_squeezy',
            'provider_mode'        => (string) config('billing.provider_mode', 'test'),
            'store_id'             => (string) config('billing.store_id'),
            'current_period_start' => Carbon::now(),
            'paid_until'           => $paidUntil ?? Carbon::now()->addYear(),
        ])->save();

        return $sub;
    }
}
