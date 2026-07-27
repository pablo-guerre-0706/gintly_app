<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseOrderItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'ordered_quantity',
        'received_quantity',
        'agreed_unit_cost',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Cantidad pendiente de recibir = ordenada − acumulada recibida.
    public function pendingQuantity(): string
    {
        return bcsub((string) $this->ordered_quantity, (string) $this->received_quantity, 3);
    }

    // True si la línea ya recibió todo lo ordenado (acumulado >= ordenado).
    public function isFullyReceived(): bool
    {
        return bccomp((string) $this->received_quantity, (string) $this->ordered_quantity, 3) >= 0;
    }
}
