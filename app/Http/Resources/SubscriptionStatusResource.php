<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DemoAccessGrant;
use App\Models\PlanSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Situación COMERCIAL del negocio autenticado. Distingue pendiente de pago, confirmación pendiente, acceso
 * habilitado y acceso vencido/restringido, con plan y vigencia cuando corresponden. No expone identificadores
 * ni secretos del proveedor.
 */
final class SubscriptionStatusResource extends JsonResource
{
    public function __construct(
        private readonly ?PlanSubscription $subscription,
        private readonly ?DemoAccessGrant $demo = null,
        private readonly ?bool $effectiveAccess = null,
    )
    {
        parent::__construct($subscription);
    }

    public function toArray(Request $request): array
    {
        $sub = $this->subscription;
        // A demonstration is not a paid subscription. Its expiry must never be reported as paid_until.
        $demo = $this->demo === null ? [] : [
            'access_source' => 'demo',
            'demo_access' => [
                'plan_key' => $this->demo->plan_key,
                'starts_at' => $this->demo->starts_at?->toIso8601String(),
                'expires_at' => $this->demo->expires_at?->toIso8601String(),
            ],
        ];

        if ($sub === null) {
            return [
                'status'        => 'none',           // nunca ha contratado
                'grants_access' => $this->effectiveAccess ?? false,
                'plan_key'      => null,
                'period'        => null,
                'paid_until'    => null,
            ] + $demo;
        }

        return [
            'status'        => $sub->status?->value,
            'grants_access' => $this->effectiveAccess ?? $sub->grantsAccessNow(),
            'plan_key'      => $sub->plan_key,
            'period'        => $sub->period?->value,
            'paid_until'    => $sub->paid_until?->toIso8601String(),
            'renews_at'     => $sub->renews_at?->toIso8601String(),
            'canceled_at'   => $sub->canceled_at?->toIso8601String(),
            'pending_change' => $sub->pending_plan_key !== null
                ? ['plan_key' => $sub->pending_plan_key, 'period' => $sub->pending_period, 'effective_at' => $sub->pending_effective_at?->toIso8601String()]
                : null,
        ] + $demo;
    }
}
