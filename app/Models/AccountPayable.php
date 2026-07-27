<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountPayableStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


final class AccountPayable extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'goods_receipt_id',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
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

    // Saldo real = total − abonado. bcmath escala 2. Nunca float.
    public function getBalanceAttribute(): string
    {
        return bcsub((string) $this->total_amount, (string) $this->paid_amount, 2);
    }

    public function isBlocked(): bool
    {
        return $this->status->isBlocked();
    }

    // True si venció: due_date en el pasado y aún queda saldo.
    public function isOverdue(): bool
    {
        if ($this->due_date === null || $this->status->isSettled()) {
            return false;
        }

        return $this->due_date->isPast() && bccomp($this->balance, '0', 2) > 0;
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->where('status', '!=', AccountPayableStatus::Pagada->value);
    }
}
