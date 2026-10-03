<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\ScopesToOperatorBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StockTransfer extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use ScopesToOperatorBranch;

    // Sucursal INDIRECTA y DOBLE: el traspaso es visible si su bodega ORIGEN o DESTINO es de la sucursal.
    protected function applyOperatorBranchScope(Builder $query, int $branchId): Builder
    {
        return $query->where(function (Builder $q) use ($branchId): void {
            $q->whereHas('fromWarehouse', fn (Builder $w) => $w->where('branch_id', $branchId))
                ->orWhereHas('toWarehouse', fn (Builder $w) => $w->where('branch_id', $branchId));
        });
    }

    protected $fillable = [
        'from_warehouse_id',
        'to_warehouse_id',
        'code',
        'status',
        'transferred_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'transferred_at' => 'datetime',
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // Líneas del traspaso, persistidas al crear y consumidas al completar.
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }
}
