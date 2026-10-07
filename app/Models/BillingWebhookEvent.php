<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identidad persistente de un evento del proveedor (deduplicación de reenvíos/versiones). UNIQUE
 * (provider, provider_mode, event_identity). processed_at NULL ⇒ recibido pero no procesado (recuperable);
 * jamás se marca procesado si la actualización local falló. parked_at ≠ NULL (con processed_at NULL) ⇒ evento
 * PROPIO aparcado por falta de correlación temporal, con su payload guardado para reproducirlo en la
 * reconciliación. Sin BelongsToBusiness (llega sin sesión).
 */
final class BillingWebhookEvent extends Model
{
    protected $fillable = [
        'provider', 'provider_mode', 'event_identity', 'event_name', 'payload', 'business_id',
        'received_at', 'parked_at', 'attempts', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at'  => 'datetime',
            'parked_at'    => 'datetime',
            'processed_at' => 'datetime',
            'attempts'     => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
