<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\PlanFeatureUnavailableException;
use App\Models\User;
use App\Services\Billing\BillingCatalog;
use App\Services\Billing\CommercialAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compuerta de CAPACIDAD por plan: exige que el plan VIGENTE incluya la feature indicada (p. ej.
 * 'receivables', 'three_way_match', 'anomalies', 'supplier_map', 'multi_branch', 'advanced_reports',
 * 'warehouse_transfers'). Se aplica DESPUÉS de EnsureActiveSubscription (ya hay vigencia). No concede módulos
 * superiores por acceder a su API. No sustituye la autorización por rol/perfil (Policies).
 *
 * Uso: ->middleware('plan.feature:receivables')
 */
final class RequiresPlanFeature
{
    public function __construct(
        private readonly CommercialAccess $access,
        private readonly BillingCatalog $catalog,
    ) {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $plan = $this->access->activePlanKey((int) $user->business_id);

            // Sin plan vigente la compuerta comercial ya habría cortado; defensivo si se usara aislada.
            if ($plan === null || ! $this->catalog->planHasFeature($plan, $feature)) {
                throw new PlanFeatureUnavailableException();
            }
        }

        return $next($request);
    }
}
