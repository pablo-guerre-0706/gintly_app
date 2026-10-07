<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
    public function __construct(private readonly ?PlanSubscription $subscription)
    {
        parent::__construct($subscription);
    }

    public function toArray(Request $request): array
    {
        $sub = $this->subscription;

        if ($sub === null) {
            return [
                'status'        => 'none',           // nunca ha contratado
                'grants_access' => false,
                'plan_key'      => null,
                'period'        => null,
                'paid_until'    => null,
            ];
        }

        return [
            'status'        => $sub->status?->value,
            'grants_access' => $sub->grantsAccessNow(),
            'plan_key'      => $sub->plan_key,
            'period'        => $sub->period?->value,
            'paid_until'    => $sub->paid_until?->toIso8601String(),
            'renews_at'     => $sub->renews_at?->toIso8601String(),
            'canceled_at'   => $sub->canceled_at?->toIso8601String(),
            'pending_change' => $sub->pending_plan_key !== null
                ? ['plan_key' => $sub->pending_plan_key, 'period' => $sub->pending_period, 'effective_at' => $sub->pending_effective_at?->toIso8601String()]
                : null,
        ];
    }
}
