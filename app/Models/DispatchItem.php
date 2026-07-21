<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchItem extends Model
{
    use HasFactory, BelongsToBusiness;

    public $timestamps = false;   // línea de documento

    protected $fillable = [
        'dispatch_id',
        'sale_item_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    // business() del trait

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
