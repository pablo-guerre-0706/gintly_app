<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Supplier extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'tax_id',
        'email',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'status'      => SupplierStatus::class,
            'approved_at' => 'datetime',
            'is_active'   => 'boolean',
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function accountsPayable(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', SupplierStatus::Aprobado->value);
    }

    // Delegación al enum para lecturas de dominio limpias.
    public function canReceiveOrders(): bool
    {
        return $this->status->canReceiveOrders();
    }

    // True si tiene órdenes o CxP, bloquea el borrado físico (RESTRICT).
    public function hasDependents(): bool
    {
        return $this->purchaseOrders()->exists() || $this->accountsPayable()->exists();
    }
}
