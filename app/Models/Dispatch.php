<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Dispatch extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'branch_id',
        'invoice_id',
        'warehouse_id',
        'user_id',
        'code',
        'received_by',
        'dispatched_at',
        'notes',
        // status → default 'registrado'; los campos de reversión los escribe el DispatchService.
    ];

    protected function casts(): array
    {
        return [
            'status'        => DispatchStatus::class,
            'dispatched_at' => 'datetime',
            'reverted_at'   => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Backstop del chk_dispatch_revert_coherence: un revertido exige quién/cuándo.
        static::saving(function (Dispatch $dispatch): void {
            if ($dispatch->status === DispatchStatus::Revertido
                && (empty($dispatch->reverted_by) || empty($dispatch->reverted_at))) {
                throw new InvalidArgumentException('Un retiro revertido requiere reverted_by y reverted_at.');
            }
        });
    }

    // business() del trait

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
