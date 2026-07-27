<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venta (carrito). code auto-generado. subtotal derivado de las líneas.
 * Nace 'abierta'. status y code fuera de fillable (control del service).
 */
final class Sale extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'table_reference',
        'notes',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => SaleStatus::class,
            'subtotal'     => 'decimal:2',
            'opened_at'    => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_sale');
    }

    public function scopeStatus(Builder $query, SaleStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function canEditItems(): bool
    {
        return $this->status->canEditItems();
    }

    public function canInvoice(): bool
    {
        return $this->status->canInvoice();
    }

    public function hasItems(): bool
    {
        return $this->items()->exists();
    }
}

