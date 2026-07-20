<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory, BelongsToBusiness, Immutable;

    const UPDATED_AT = null;   // solo INSERT

    protected $fillable = [
        'invoice_id', 'cash_session_id', 'user_id',
        'payment_method', 'amount', 'reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount'         => 'decimal:2',
            'paid_at'        => 'datetime',
            'created_at'     => 'immutable_datetime',
        ];
    }

    // business() del trait; inmutabilidad del trait Immutable.
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
