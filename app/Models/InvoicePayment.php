<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de factura. Append-only (Immutable): un cobro no se edita ni se borra.
 */
final class InvoicePayment extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'cash_session_id',
        'user_id',
        'payment_method',
        'amount',
        'reference',
        'paid_at',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
