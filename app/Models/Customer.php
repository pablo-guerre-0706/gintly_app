<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Exceptions\CustomerHasReceivablesException;
use App\Exceptions\ProtectedResourceException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'name',
        'document_type',
        'document_number',
        'email',
        'phone_number',
        'birth_date',
        'is_active',
        'credit_limit',
        'notes',
        // 'is_generic' EXCLUIDO: solo el sistema lo marca (asignación directa en el Observer).
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'birth_date'    => 'date',
            'is_generic'    => 'boolean',
            'is_active'     => 'boolean',
            'credit_limit'  => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // ERR-05: el "Consumidor Final" es intocable.
        static::updating(function (Customer $customer): void {
            if ($customer->getOriginal('is_generic')) {
                throw new ProtectedResourceException('El cliente genérico "Consumidor Final" no puede modificarse.');
            }
        });

        static::deleting(function (Customer $customer): void {
            if ($customer->is_generic) {
                throw new ProtectedResourceException('El cliente genérico "Consumidor Final" no puede eliminarse.');
            }
            // ERR-05B: no dejar deuda huérfana (activación real en MOD-08).
            if ($customer->hasPendingReceivables()) {
                throw new CustomerHasReceivablesException;
            }
        });
    }

    public function hasPendingReceivables(): bool
    {
        return $this->accountsReceivable()
            ->whereIn('status', ['pendiente', 'parcial', 'vencida'])
            ->where('balance', '>', 0)
            ->exists();
    }

    /** Excluye el genérico de los listados de clientes reales. */
    public function scopeReal(Builder $query): void
    {
        $query->where('is_generic', false);
    }

    // business() del trait

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    // RESERVADO — se cablean en su módulo:
    //   sales(): hasMany(Sale)               → MOD-07
    //   invoices(): hasMany(Invoice)         → MOD-07
}
