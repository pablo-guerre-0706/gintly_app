<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CashSession extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'cash_register_id',
        'opening_amount',
        'opening_amount_usd',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opening_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'counted_denominations' => 'array',
            // Doble moneda: leg USD reconciliado de forma independiente del NIO.
            'opening_amount_usd' => 'decimal:2',
            'expected_amount_usd' => 'decimal:2',
            'counted_amount_usd' => 'decimal:2',
            'difference_usd' => 'decimal:2',
            'counted_denominations_usd' => 'array',
            'session_exchange_rate' => 'decimal:6',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** Historial de arqueos ciegos independientes realizados durante la sesión (append-only). */
    public function counts(): HasMany
    {
        return $this->hasMany(CashCount::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CashSessionStatus::Abierta->value);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
