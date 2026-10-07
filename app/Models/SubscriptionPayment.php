<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia normalizada de un pago de suscripción que cubre un período concreto (SaaS; NO es un pago del ERP).
 * UNIQUE (provider, provider_mode, provider_payment_id) evita doble conteo. Guarda importe/moneda COBRADOS
 * (p. ej. USD de Lemon Squeezy) y, de referencia, el importe ANUNCIADO del catálogo (centavos NIO). Sin
 * tarjetas, CVV, credenciales ni payloads completos.
 */
final class SubscriptionPayment extends Model
{
    protected $fillable = [
        'business_id', 'plan_subscription_id', 'provider', 'provider_mode', 'provider_payment_id',
        'event_identity', 'status', 'amount_minor', 'currency', 'catalog_amount_minor', 'catalog_currency',
        'period_start', 'period_end',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor'         => 'integer',
            'catalog_amount_minor' => 'integer',
            'period_start'         => 'datetime',
            'period_end'           => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlanSubscription::class, 'plan_subscription_id');
    }
}
