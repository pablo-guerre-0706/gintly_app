<?php

namespace App\Models;

use App\Enums\ReturnDestination;
use App\Enums\ReturnReasonCode;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory, BelongsToBusiness;

    public $timestamps = false;   // línea de documento

    protected $fillable = [
        'sales_return_id', 'product_id', 'sale_item_id', 'warehouse_id',
        'quantity', 'unit_price', 'destination', 'reason_code',
        // line_total → derivado en booted.
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

    protected static function booted(): void
    {
        static::saving(function (SalesReturnItem $item): void {
            $item->line_total = bcmul((string) $item->quantity, (string) $item->unit_price, 2);
        });
    }

    // business() del trait
    public function salesReturn(): BelongsTo 
    { 
        return $this->belongsTo(SalesReturn::class); 
    }
    
    public function product(): BelongsTo
    { 
        return $this->belongsTo(Product::class); 
    }

    public function saleItem(): BelongsTo
    { 
        return $this->belongsTo(SaleItem::class); 
    }

    public function warehouse(): BelongsTo
    { 
        return $this->belongsTo(Warehouse::class); 
    }
}
