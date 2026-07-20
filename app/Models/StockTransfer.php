<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class StockTransfer extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'from_warehouse_id',
        'to_warehouse_id',
        'user_id',
        'code',
        'status',
        'transferred_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'         => StockTransferStatus::class,
            'transferred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Backstop de app del chk_transfer_diff_warehouse: mensaje legible antes de golpear el motor.
        static::saving(function (StockTransfer $transfer): void {
            if ((int) $transfer->from_warehouse_id === (int) $transfer->to_warehouse_id) {
                throw new InvalidArgumentException('Origen y destino del traspaso no pueden ser la misma bodega.');
            }
        });
    }

    // business() del trait

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
}
