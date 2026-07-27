<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


final class SaleItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'unit_cost',
        'is_taxable',
        'discount_amount',
        'line_total',
        'recipe_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity'            => 'decimal:3',
            'unit_price'          => 'decimal:2',
            'unit_cost'           => 'decimal:4',
            'is_taxable'          => 'boolean',
            'discount_amount'     => 'decimal:2',
            'line_total'          => 'decimal:2',
            'recipe_snapshot'     => 'array',
            'dispatched_quantity' => 'decimal:3',
            'returned_quantity'   => 'decimal:3',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Cantidad aún no entregada = facturada − despachada. Accesor P10 (MOD-09).
    public function pendingQuantity(): string
    {
        return bcsub((string) $this->quantity, (string) ($this->dispatched_quantity ?? '0'), 3);
    }

    // Cantidad devolvible = despachada − devuelta. Base de MOD-10.
    public function returnableQuantity(): string
    {
        return bcsub((string) ($this->dispatched_quantity ?? '0'), (string) ($this->returned_quantity ?? '0'), 3);
    }

    /**
     * True si la línea corresponde a un producto compuesto (tiene receta
     * congelada). Rige la reserva y el retiro de insumos.
     */
    public function isCompound(): bool
    {
        return ! empty($this->recipe_snapshot);
    }
}
