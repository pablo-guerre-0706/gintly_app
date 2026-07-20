<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'user_id',
        'code',
        'status',
        'expected_total',   // recalculado por el Service desde los ítems (vacío #7)
        'ordered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'         => PurchaseOrderStatus::class,
            'expected_total' => 'decimal:2',
            'ordered_at'     => 'date',
        ];
    }

    // business() del trait

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function accountsPayable(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
