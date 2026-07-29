<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteResolutionType;
use App\Enums\CreditNoteStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CreditNote extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'invoice_id',
        'sales_return_id',
        'customer_id',
        'cash_session_id',
        'resolution_type',
        'total_amount',
        'tax_amount',
    ];
    // FUERA de fillable: folio, status, issued_by, issued_at (los fija el Service).

    protected function casts(): array
    {
        return [
            'resolution_type' => CreditNoteResolutionType::class,
            'status'          => CreditNoteStatus::class,
            'total_amount'    => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'issued_at'       => 'immutable_datetime',
        ];
    }

    public function invoice(): BelongsTo     { return $this->belongsTo(Invoice::class); }
    public function salesReturn(): BelongsTo { return $this->belongsTo(SalesReturn::class); }
    public function customer(): BelongsTo    { return $this->belongsTo(Customer::class); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }
    public function issuedBy(): BelongsTo    { return $this->belongsTo(User::class, 'issued_by'); }

    /** ¿Es una NC de saldo a favor vigente (fuente de verdad del crédito disponible, Fase 1)? */
    public function isOpenBalance(): bool
    {
        return $this->resolution_type === CreditNoteResolutionType::NotaCreditoSaldo
            && $this->status === CreditNoteStatus::Emitida;
    }
}

