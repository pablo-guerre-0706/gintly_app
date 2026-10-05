<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-06 · Arqueo ciego independiente (RF-06-04). Evidencia inmutable de un conteo físico realizado
 * durante una sesión ABIERTA, sin cerrarla. Append-only (sin updated_at): cualquier UPDATE/DELETE →
 * ImmutableRecordException (403). difference es columna generada (counted − expected).
 */
final class CashCount extends Model
{
    use BelongsToBusiness;
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'cash_session_id',
        'user_id',
        'counted_amount',
        'expected_amount',
        'counted_denominations',
        'counted_amount_usd',
        'expected_amount_usd',
        'counted_denominations_usd',
        'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'counted_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'counted_denominations' => 'array',
            // Doble moneda: leg USD del arqueo, reconciliado de forma independiente.
            'counted_amount_usd' => 'decimal:2',
            'expected_amount_usd' => 'decimal:2',
            'difference_usd' => 'decimal:2',
            'counted_denominations_usd' => 'array',
            'counted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
