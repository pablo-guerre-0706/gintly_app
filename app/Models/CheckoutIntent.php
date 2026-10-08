<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correlación de un intento de contratación (checkout alojado). Idempotencia PROPIA por `idempotency_key`
 * (UUID, independiente de registration_requests). Sin BelongsToBusiness: business_id explícito de la sesión.
 */
final class CheckoutIntent extends Model
{
    protected $fillable = [
        'business_id', 'idempotency_key', 'plan_key', 'period', 'provider', 'provider_mode', 'store_id',
        'provider_variant_id', 'status', 'provider_checkout_id', 'checkout_url', 'expires_at', 'fingerprint',
    ];

    protected function casts(): array
    {
        return ['period' => BillingPeriod::class, 'expires_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
