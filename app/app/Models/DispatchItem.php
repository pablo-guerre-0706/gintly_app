<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DispatchItem extends Model
{
    use BelongsToBusiness;

    public $timestamps = false; // Tabla sin timestamps (diccionario).

    protected $fillable = [
        'dispatch_id',
        'sale_item_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', // bcmath escala 3.
        ];
    }

    public function dispatch(): BelongsTo  { return $this->belongsTo(Dispatch::class); }
    public function saleItem(): BelongsTo  { return $this->belongsTo(SaleItem::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
}
