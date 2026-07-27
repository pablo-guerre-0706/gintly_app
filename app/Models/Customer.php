<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


// Cliente. El "Consumidor Final" (is_generic=true), singleton del sistema, sembrado por BusinessObserver.
final class Customer extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'document_type',
        'document_number',
        'email',
        'phone_number',
        'birth_date',
        'credit_limit',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'birth_date'    => 'date',
            'credit_limit'  => 'decimal:2',
            'is_generic'    => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    // accountsReceivable(): se activa al cerrar MOD-08 (parche P6).
    // sales(), invoices(): se activan al cerrar MOD-07.


    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_generic', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // True si es el Consumidor Final del sistema: no editable ni eliminable.
    public function isProtected(): bool
    {
        return $this->is_generic === true;
    }

    public function operatesOnCredit(): bool
    {
        return bccomp((string) $this->credit_limit, '0', 2) > 0;
    }

    public function hasPendingReceivables(): bool
    {
        return false;
    }
}

