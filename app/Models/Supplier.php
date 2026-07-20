<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'name',
        'tax_id',
        'email',
        'phone',
        'status',
        'approved_by',   // se puebla SOLO al aprobar (ROL-01, vía Service)
        'approved_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status'      => SupplierStatus::class,
            'approved_at' => 'datetime',
            'is_active'   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Coherencia de aprobación (vacío #4): el estado y sus metadatos no pueden mentir.
        static::saving(function (Supplier $supplier): void {
            if ($supplier->status === SupplierStatus::Aprobado) {
                // Un aprobado SIN quién/ cuándo lo aprobó es un dato corrupto.
                if (empty($supplier->approved_by)) {
                    throw new \InvalidArgumentException('Un proveedor aprobado requiere approved_by (ROL-01).');
                }
                $supplier->approved_at ??= now();
            } else {
                // Si no está aprobado, limpiamos los metadatos de aprobación.
                $supplier->approved_by = null;
                $supplier->approved_at = null;
            }
        });
    }

    // business() del trait

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
}
