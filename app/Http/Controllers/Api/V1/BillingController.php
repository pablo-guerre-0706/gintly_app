<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\CancelSubscriptionRequest;
use App\Http\Requests\Api\V1\Billing\CheckoutRequest;
use App\Http\Requests\Api\V1\Billing\PlanChangeRequest;
use App\Http\Resources\CheckoutResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionStatusResource;
use App\Services\Billing\BillingCatalog;
use App\Services\Billing\CommercialAccess;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API de contratación (/api/v1/billing). GET no inicia cobros ni escribe dominio. El checkout exige sesión
 * Sanctum + CSRF + propietario real ROL-01 (CheckoutRequest). El backend resuelve precio/moneda/tienda/variante.
 */
final class BillingController extends Controller
{
    public function __construct(
        private readonly BillingCatalog $catalog,
        private readonly CommercialAccess $access,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    /** Catálogo disponible para contratar (precios NIO anunciados). Solo lectura. */
    public function plans(): AnonymousResourceCollection
    {
        $plans = [];
        foreach ($this->catalog->planKeys() as $key) {
            $def = (array) config("billing.catalog.{$key}");
            $plans[] = [
                'key'    => $key,
                'name'   => (string) $def['name'],
                'prices' => [
                    'monthly' => $this->price($key, 'monthly'),
                    'annual'  => $this->price($key, 'annual'),
                ],
                'limits'   => $def['limits'],
                'features' => $this->catalog->features($key),
            ];
        }

        return PlanResource::collection($plans);
    }

    /** Situación comercial del negocio de la sesión. Solo lectura. */
    public function subscription(Request $request): SubscriptionStatusResource
    {
        $businessId = (int) $request->user()->business_id;

        return new SubscriptionStatusResource(
            $this->access->subscriptionFor($businessId),
            $this->access->hasPaidAccess($businessId) ? null : $this->access->demoGrantFor($businessId),
            $this->access->grantsAccess($businessId),
        );
    }

    /** Crea o recupera un intento de contratación y devuelve la URL del checkout alojado. */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $intent = $this->subscriptions->checkout(
            $request->user()->business,
            (string) $request->validated('plan'),
            (string) $request->validated('period'),
            $request->idempotencyKey(),
            (string) $request->user()->email,
        );

        return CheckoutResource::make($intent)->response()->setStatusCode(201);
    }

    /** Cambia de plan CONSERVANDO la misma suscripción (ascenso tras pago; descenso al cierre del período). */
    public function change(PlanChangeRequest $request): SubscriptionStatusResource
    {
        $sub = $this->subscriptions->requestPlanChange(
            $request->user()->business,
            (string) $request->validated('plan'),
            (string) $request->validated('period'),
        );

        return new SubscriptionStatusResource($sub);
    }

    /** Cancela las renovaciones de la misma suscripción (conserva el acceso hasta el fin del período pagado). */
    public function cancel(CancelSubscriptionRequest $request): SubscriptionStatusResource
    {
        $sub = $this->subscriptions->requestCancellation($request->user()->business);

        return new SubscriptionStatusResource($sub);
    }

    /** @return array{nio_minor:int, nio:string} */
    private function price(string $plan, string $period): array
    {
        $minor = (int) config("billing.catalog.{$plan}.prices.{$period}.nio_minor", 0);

        // Decimal exacto por bcmath (centavos → unidades), sin floats.
        return ['nio_minor' => $minor, 'nio' => bcdiv((string) $minor, '100', 2)];
    }
}
