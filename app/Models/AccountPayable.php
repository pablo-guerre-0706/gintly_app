<?php

namespace App\Models;

use App\Enums\AccountPayableStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayable extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'goods_receipt_id',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
        'unblocked_by',   // se puebla SOLO al desbloquear (ROL-01, vía Service)
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount'  => 'decimal:2',
            'status'       => AccountPayableStatus::class,
            'due_date'     => 'date',
        ];
    }

    /** Saldo pendiente de pago al proveedor = total - pagado (bcmath). */
    protected function balance(): Attribute
    {
        return Attribute::make(
            get: fn (): string => bcsub((string) $this->total_amount, (string) $this->paid_amount, 2),
        );
    }

    // business() del trait

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }
}
