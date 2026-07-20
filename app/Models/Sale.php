<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'branch_id', 'customer_id', 'user_id', 'code',
        'table_reference', 'notes', 'opened_at',
        // status → default 'abierta'; subtotal y confirmed_at los escribe el SaleService.
    ];

    protected function casts(): array
    {
        return [
            'status'       => SaleStatus::class,
            'subtotal'     => 'decimal:2',
            'opened_at'    => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    // business() del trait
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(SaleItem::class); }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_sale')->withPivot('business_id');
    }
}
