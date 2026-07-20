<?php

namespace App\Models;

use App\Enums\PhysicalCountStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysicalCount extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'user_id',
        'system_quantity',
        'counted_quantity',
        // 'difference' excluido, la calcula el motor (columna generada, FIX auditoría #2).
        'status',
        'notes',
        'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity'  => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'difference'       => 'decimal:3',   // read-only, el cast solo formatea lectura
            'status'           => PhysicalCountStatus::class,
            'counted_at'       => 'datetime',
        ];
    }

    // business() del trait

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
