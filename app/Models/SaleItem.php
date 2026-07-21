<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory, BelongsToBusiness;

    public $timestamps = false;   // línea de documento

    protected $fillable = [
        'sale_id', 'product_id', 'description',
        'quantity', 'unit_price', 'unit_cost',
        'discount_amount', 'recipe_snapshot',
        // line_total → lo deriva el booted (nunca el cliente).
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'decimal:3',
            'unit_price'      => 'decimal:2',
            'unit_cost'       => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'line_total'      => 'decimal:2',
            'recipe_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // line_total = quantity * unit_price - discount (bcmath, sin float).
        static::saving(function (SaleItem $item): void {
            $gross = bcmul((string) $item->quantity, (string) $item->unit_price, 2);
            $item->line_total = bcsub($gross, (string) $item->discount_amount, 2);
        });
    }

    protected function pendingQuantity(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn (): string => bcsub((string) $this->quantity, (string) $this->dispatched_quantity, 3),
        );
    }


    // business() del trait
    public function sale(): BelongsTo
    { 
        return $this->belongsTo(Sale::class);
    }
    
    public function product(): BelongsTo
    { 
        return $this->belongsTo(Product::class);
    }

    public function dispatchItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }
}
