<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use App\Models\Dispatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryMovement extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'user_id',
        'type',
        'quantity',
        'balance_after',
        'unit_cost',
        'stock_transfer_id',
        'inventory_adjustment_id',
        'purchase_order_id',
        'dispatch_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'type'          => InventoryMovementType::class,
            'quantity'      => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost'     => 'decimal:4',
            'created_at'    => 'immutable_datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }
    
    public function scopeOfType(Builder $query, InventoryMovementType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
