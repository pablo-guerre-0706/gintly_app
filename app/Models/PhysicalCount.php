<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PhysicalCountStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\ScopesToAssignedWarehouses;
use App\Models\Concerns\ScopesToOperatorBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PhysicalCount extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use ScopesToAssignedWarehouses; // visibilidad ROL-03 por bodega asignada (más estricto que sucursal)
    use ScopesToOperatorBranch;

    // Sucursal INDIRECTA: por la bodega del conteo.
    protected function applyOperatorBranchScope(Builder $query, int $branchId): Builder
    {
        return $query->whereHas('warehouse', fn (Builder $w) => $w->where('branch_id', $branchId));
    }

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'counted_quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'difference' => 'decimal:3',
            'status' => PhysicalCountStatus::class,
            'counted_at' => 'datetime',
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

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }
}
