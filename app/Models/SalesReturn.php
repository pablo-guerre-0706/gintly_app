<?php

namespace App\Models;

use App\Enums\SalesReturnStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesReturn extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'branch_id', 'invoice_id', 'customer_id', 'user_id',
        'code', 'total_returned', 'returned_at', 'notes',
        // status → default 'registrada'; lo mueve el ReturnService.
    ];

    protected function casts(): array
    {
        return [
            'status'         => SalesReturnStatus::class,
            'total_returned' => 'decimal:2',
            'returned_at'    => 'datetime',
        ];
    }

    // business() del trait
    public function branch(): BelongsTo
    { 
        return $this->belongsTo(Branch::class); 
    }
    
    public function invoice(): BelongsTo
    { 
        return $this->belongsTo(Invoice::class); 
    }
    
    public function customer(): BelongsTo
    { 
        return $this->belongsTo(Customer::class); 
    }

    public function user(): BelongsTo 
    { 
        return $this->belongsTo(User::class); 
    }

    public function items(): HasMany 
    { 
        return $this->hasMany(SalesReturnItem::class); 
    }

    public function creditNote(): HasOne 
    { 
        return $this->hasOne(CreditNote::class); 
    }
}
