<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReceivablePayment extends Model
{
    use BelongsToBusiness;
    use Immutable; // Append-only: bloquea UPDATE/DELETE (ImmutableRecordException 403).

    public const UPDATED_AT = null; // Sin updated_at: la fila solo se inserta.

    protected $fillable = [
        'accounts_receivable_id',
        'cash_session_id',
        'amount',
        'payment_method',
        'reference',
        'paid_at',
    ];
    // user_id NUNCA por request (no-repudio): lo fija el Service desde la sesión autenticada.

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'paid_at'        => 'immutable_datetime',
        ];
    }

    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class, 'accounts_receivable_id');
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

