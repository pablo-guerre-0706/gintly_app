<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryAdjustmentType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


final class InventoryAdjustment extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'physical_count_id',
        'type',
        'reason',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'type'        => InventoryAdjustmentType::class,
            'adjusted_at' => 'datetime',
        ];
    }

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
