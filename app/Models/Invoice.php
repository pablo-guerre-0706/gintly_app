<?php

namespace App\Models;

use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\ImmutableInvoiceException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'branch_id', 'customer_id', 'cash_session_id', 'issued_by',
        'payment_type', 'subtotal', 'tax_amount', 'discount_amount',
        'total', 'paid_amount', 'payment_status', 'issued_at',
        // folio, status y campos de anulación: asignación directa por el InvoiceService.
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

    protected static function booted(): void
    {
        // ERR-07B: el núcleo fiscal es inmutable. Se permite mutar SOLO paid_amount,
        // payment_status y la transición de anulación (status + voided_*).
        static::updating(function (Invoice $invoice): void {
            $frozen = ['folio', 'subtotal', 'tax_amount', 'discount_amount', 'total',
                       'issued_at', 'customer_id', 'branch_id', 'payment_type', 'issued_by'];
            foreach ($frozen as $field) {
                if ($invoice->isDirty($field)) {
                    throw new ImmutableInvoiceException("El campo '{$field}' de una factura emitida es inmutable.");
                }
            }
        });
    }

    // business() del trait
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }
    public function issuedBy(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function voidedBy(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
    public function payments(): HasMany { return $this->hasMany(InvoicePayment::class); }

    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class, 'invoice_sale')->withPivot('business_id');
    }

    // RESERVADO — MOD-08: accountReceivable(): hasOne(AccountReceivable).
    // RESERVADO — MOD-10: creditNotes(): hasMany(CreditNote).
}
