<?php

namespace App\Models;

use App\Enums\CreditNoteResolutionType;
use App\Enums\CreditNoteStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'invoice_id', 'sales_return_id', 'customer_id', 'cash_session_id',
        'issued_by', 'resolution_type', 'total_amount', 'tax_amount', 'issued_at',
        // folio y status: asignación directa por el ReturnService.
    ];

    protected function casts(): array
    {
        return [
            'resolution_type' => CreditNoteResolutionType::class,
            'status'          => CreditNoteStatus::class,
            'total_amount'    => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'issued_at'       => 'datetime',
        ];
    }

    // business() del trait
    public function invoice(): BelongsTo
    { 
        return $this->belongsTo(Invoice::class); 
    }
    
    public function salesReturn(): BelongsTo 
    { 
        return $this->belongsTo(SalesReturn::class);
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
}
