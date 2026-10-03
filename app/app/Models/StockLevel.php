<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockLevel extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const CREATED_AT = null;

    protected $fillable = [
        'min_stock',
        'max_stock',
    ];

    protected function casts(): array
    {
        return [
            'quantity'          => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'min_stock'         => 'decimal:3',
            'max_stock'         => 'decimal:3',
            'average_cost'      => 'decimal:4',
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

    public function getAvailableAttribute(): string
    {
        return bcsub((string) $this->quantity, (string) $this->reserved_quantity, 3);
    }

    public function isBelowMin(): bool
    {
        if ($this->min_stock === null) {
            return false;
        }

        return bccomp((string) $this->quantity, (string) $this->min_stock, 3) < 0;
    }

    public function scopeBelowMin(Builder $query): Builder
    {
        return $query->whereNotNull('min_stock')
            ->whereColumn('quantity', '<', 'min_stock');
    }
}
