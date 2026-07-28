<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DispatchStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Dispatch extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'branch_id',
        'invoice_id',
        'warehouse_id',
        'received_by',
        'notes',
    ];
    // FUERA de fillable: code, status, user_id, dispatched_at, reverted_by/at, revert_reason.

    protected function casts(): array
    {
        return [
            'status'        => DispatchStatus::class,
            'dispatched_at' => 'immutable_datetime',
            'reverted_at'   => 'immutable_datetime',
        ];
    }

    // ---------------- Relaciones ----------------
    public function branch(): BelongsTo    { return $this->belongsTo(Branch::class); }
    public function invoice(): BelongsTo   { return $this->belongsTo(Invoice::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    // ---------------- Predicados ----------------
    public function isReverted(): bool
    {
        return $this->status === DispatchStatus::Revertido;
    }

    public function canRevert(): bool
    {
        return $this->status->canRevert();
    }
}
