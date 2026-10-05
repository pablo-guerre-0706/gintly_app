<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-06 · Tipo de cambio versionado por vigencia (NIO por 1 unidad de `currency`). Historial inmutable:
 * UPDATE/DELETE → ImmutableRecordException (403). La tasa vigente se resuelve en ExchangeRateService.
 */
final class ExchangeRate extends Model
{
    use BelongsToBusiness;
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'currency',
        'rate',
        'effective_from',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'currency'       => Currency::class,
            'rate'           => 'decimal:6',
            'effective_from' => 'datetime',
            'created_at'     => 'immutable_datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
