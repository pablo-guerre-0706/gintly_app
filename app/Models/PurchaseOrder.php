<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PurchaseOrder extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'code',
        'status',
        'expected_total',
        'ordered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'         => PurchaseOrderStatus::class,
            'expected_total' => 'decimal:2',
            'ordered_at'     => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function accountsPayable(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function canReceive(): bool
    {
        return $this->status->canReceive();
    }
}
