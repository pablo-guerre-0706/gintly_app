<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoodsReceiptMatchStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\ScopesToOperatorBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GoodsReceipt extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use ScopesToOperatorBranch;

    // Sucursal INDIRECTA: por la bodega de la recepción.
    protected function applyOperatorBranchScope(Builder $query, int $branchId): Builder
    {
        return $query->whereHas('warehouse', fn (Builder $w) => $w->where('branch_id', $branchId));
    }

    protected $fillable = [
        'purchase_order_id',
        'warehouse_id',
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
            'match_status' => GoodsReceiptMatchStatus::class,
            'received_at' => 'datetime',
        ];
    }

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

    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'id', 'goods_receipt_id');
    }
}
