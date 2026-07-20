<?php

namespace App\Models;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory, BelongsToBusiness, Immutable;

    // Movimiento de caja: solo INSERT. Sin updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'cash_session_id',
        'user_id',
        'type',
        'category',
        'payment_method',
        'amount',
        'sale_id',          // FK diferida (MOD-07) — columna plana por ahora
        'authorized_by',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type'           => CashMovementType::class,
            'category'       => CashMovementCategory::class,
            'payment_method' => PaymentMethod::class,
            'amount'         => 'decimal:2',
            'created_at'     => 'immutable_datetime',
        ];
    }

    // business() del trait; inmutabilidad del trait Immutable.

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
