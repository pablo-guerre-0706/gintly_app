<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory, BelongsToBusiness;

    // Línea de documento: sin columnas de tiempo.
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'ordered_quantity',
        'received_quantity',
        'agreed_unit_cost',   // pilar del 3-Way Match (costo pactado)
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity'  => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'agreed_unit_cost'  => 'decimal:4',
            'line_total'        => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // line_total siempre coherente: ordered_quantity * agreed_unit_cost (bcmath, sin float).
        static::saving(function (PurchaseOrderItem $item): void {
            $item->line_total = bcmul(
                (string) $item->ordered_quantity,
                (string) $item->agreed_unit_cost,
                2
            );
        });
    }

    // business() del trait

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
