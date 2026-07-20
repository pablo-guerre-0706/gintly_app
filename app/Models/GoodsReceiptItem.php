<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasFactory, BelongsToBusiness;

    public $timestamps = false;   // línea de documento (evidencia de recepción)

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'received_quantity',
        'invoiced_unit_cost',
        'line_total',
        'matched',
    ];

    protected function casts(): array
    {
        return [
            'received_quantity'  => 'decimal:3',
            'invoiced_unit_cost' => 'decimal:4',
            'line_total'         => 'decimal:2',
            'matched'            => 'boolean',
        ];
    }

    // business() del trait

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
