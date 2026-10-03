<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnDestination;
use App\Enums\ReturnReasonCode;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesReturnItem extends Model
{
    use BelongsToBusiness;

    public $timestamps = false; // Sin timestamps (diccionario).

    protected $fillable = [
        'sales_return_id',
        'product_id',
        'sale_item_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'destination',
        'reason_code',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:3',
            'unit_price'  => 'decimal:2',
            'line_total'  => 'decimal:2',
            'destination' => ReturnDestination::class,
            'reason_code' => ReturnReasonCode::class,
        ];
    }

    public function salesReturn(): BelongsTo { return $this->belongsTo(SalesReturn::class); }
    public function product(): BelongsTo     { return $this->belongsTo(Product::class); }
    public function saleItem(): BelongsTo    { return $this->belongsTo(SaleItem::class); }
    public function warehouse(): BelongsTo   { return $this->belongsTo(Warehouse::class); }
}
