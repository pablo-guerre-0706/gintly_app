<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivablePayment extends Model
{
    use HasFactory, BelongsToBusiness, Immutable;

    const UPDATED_AT = null;   // solo INSERT

    protected $fillable = [
        'accounts_receivable_id',
        'cash_session_id',
        'user_id',
        'amount',
        'payment_method',
        'reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'paid_at'        => 'datetime',
            'created_at'     => 'immutable_datetime',
        ];
    }

    // business() del trait; inmutabilidad del trait Immutable.

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
