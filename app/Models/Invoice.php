<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\ImmutableInvoiceException;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\AccountReceivable;
use App\Models\Dispatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Factura: núcleo fiscal INMUTABLE PARCIAL (D-29, H-66).
 */
final class Invoice extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    /**
     * Campos del núcleo fiscal: una vez emitida la factura, no cambian jamás.
     *
     * @var array<int, string>
     */
    private const FROZEN_FIELDS = [
        'folio',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'issued_at',
        'customer_id',
        'branch_id',
        'payment_type',
        'issued_by',
    ];

    protected $fillable = [
        'branch_id',
        'customer_id',
        'cash_session_id',
        'folio',
        'payment_type',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_type'    => InvoicePaymentType::class,
            'payment_status'  => InvoicePaymentStatus::class,
            'status'          => InvoiceStatus::class,
            'subtotal'        => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total'           => 'decimal:2',
            'paid_amount'     => 'decimal:2',
            'issued_at'       => 'datetime',
            'voided_at'       => 'datetime',
        ];
    }

    /**
     * Guarda de inmutabilidad parcial: bloquea la modificación de cualquier
     * campo congelado sobre una factura ya existente (H-66). Los campos mutables
     * (paid_amount, payment_status, status, voided_*) pasan sin objeción.
     */
    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            foreach (self::FROZEN_FIELDS as $field) {
                if ($invoice->isDirty($field)) {
                    throw ImmutableInvoiceException::field($field);
                }
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class, 'invoice_sale');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function accountReceivable(): HasOne
    {
        return $this->hasOne(AccountReceivable::class);
    }

    public function scopeEmitida(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Emitida->value);
    }

    public function isVoided(): bool
    {
        return $this->status->isVoided();
    }

    public function canVoid(): bool
    {
        return $this->status->canVoid();
    }

    /**
     * Saldo pendiente de cobro = total − pagado. bcmath e2.
     */
    public function outstandingBalance(): string
    {
        return bcsub((string) $this->total, (string) $this->paid_amount, 2);
    }
}
