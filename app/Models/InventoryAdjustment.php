<?php

namespace App\Models;

use App\Enums\InventoryAdjustmentType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'warehouse_id',
        'user_id',
        'physical_count_id',   // nullable: el ajuste puede nacer de un conteo (RF-03-03) o ser directo
        'type',
        'reason',              // motivo obligatorio (RF-03-02)
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'type'        => InventoryAdjustmentType::class,
            'adjusted_at' => 'datetime',
        ];
    }

    // business() del trait

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function physicalCount(): BelongsTo
    {
        return $this->belongsTo(PhysicalCount::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
