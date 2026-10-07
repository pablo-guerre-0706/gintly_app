<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Estado COMERCIAL vigente de un negocio (una fila por negocio; el historial de pagos/eventos vive en las
 * tablas relacionadas). NO usa BelongsToBusiness: se consulta desde la compuerta (por business_id explícito
 * de la sesión) y desde el webhook (sin sesión), nunca bajo BusinessScope de Auth.
 */
final class PlanSubscription extends Model
{
    protected $fillable = [
        'business_id', 'plan_key', 'period', 'status', 'provider', 'provider_mode', 'store_id',
        'provider_subscription_id', 'provider_variant_id', 'current_period_start', 'paid_until',
        'renews_at', 'canceled_at', 'pending_plan_key', 'pending_period', 'pending_variant_id', 'pending_effective_at',
    ];

    protected function casts(): array
    {
        return [
            'status'               => SubscriptionStatus::class,
            'period'               => BillingPeriod::class,
            'current_period_start' => 'datetime',
            'paid_until'           => 'datetime',
            'renews_at'            => 'datetime',
            'canceled_at'          => 'datetime',
            'pending_effective_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * ¿Concede acceso operativo AHORA? Exige estado que pueda conceder acceso Y vigencia pagada no vencida.
     * El estado por sí solo nunca basta; paid_until gobierna el vencimiento aunque no llegue otra notificación.
     */
    public function grantsAccessNow(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->status instanceof SubscriptionStatus
            && $this->status->mayGrantAccess()
            && $this->paid_until !== null
            && $now->lessThan($this->paid_until);
    }
}
