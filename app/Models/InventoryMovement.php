<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory, BelongsToBusiness, Immutable;

    // Kardex: solo INSERT. Sin updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'user_id',                  // nullable: ROL-SYS puede postear movimientos automáticos
        'type',
        'quantity',
        'balance_after',            // saldo tras el asiento (foto del kardex)
        'unit_cost',
        'stock_transfer_id',
        'inventory_adjustment_id',
        'purchase_order_id',        // FK diferida (MOD-04) — columna plana por ahora
        'dispatch_id',              // FK diferida (MOD-09) — columna plana por ahora
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

    // business() del trait; inmutabilidad del trait Immutable.

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

    // RESERVADO — se cablean en su módulo (apuntan a modelos aún inexistentes):
    //   purchaseOrder(): belongsTo(PurchaseOrder) → MOD-04
    //   dispatch():      belongsTo(Dispatch)      → MOD-09
}
