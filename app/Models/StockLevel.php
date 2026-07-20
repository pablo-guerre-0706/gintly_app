<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory, BelongsToBusiness;

    // Solo updated_at (saldo que se toca, nunca se "crea" dos veces).
    const CREATED_AT = null;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',            // solo InventoryService debe escribir esto (con lockForUpdate).
        'min_stock',
        'max_stock',
        'reserved_quantity',   // idem
        'average_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity'          => 'decimal:3',
            'min_stock'         => 'decimal:3',
            'max_stock'         => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'average_cost'      => 'decimal:4',
        ];
    }

    /**
     * Disponible = físico − reservado. Cifra que gobierna ERR-03.
     * bcsub preserva precisión (bcmath estándar en tu build PHP 8.5).
     */
    protected function available(): Attribute
    {
        return Attribute::make(
            get: fn (): string => bcsub((string) $this->quantity, (string) $this->reserved_quantity, 3),
        );
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
}
