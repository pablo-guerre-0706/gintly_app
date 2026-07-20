<?php

namespace App\Models;

use App\Enums\GoodsReceiptMatchStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceipt extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'purchase_order_id',
        'warehouse_id',
        'user_id',
        'supplier_invoice_number',
        'supplier_invoice_total',
        'match_status',
        'received_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'supplier_invoice_total' => 'decimal:2',
            'match_status'           => GoodsReceiptMatchStatus::class,
            'received_at'            => 'datetime',
        ];
    }

    // business() del trait

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
