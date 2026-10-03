<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxClass;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regla fiscal del negocio: resuelve la tasa de una clase fiscal con ámbito general
 * (branch_id NULL) o por sucursal. Versionada: al desactivarse permanece para
 * trazabilidad; el candado uniq_active_tax_rule_scope garantiza una sola activa por
 * (negocio, clase, ámbito).
 */
final class TaxRule extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'tax_class',
        'branch_id',
        'rate',
        'is_active',
    ];
    // business_id lo inyecta BelongsToBusiness; active_scope_lock lo deriva el motor.

    protected function casts(): array
    {
        return [
            'tax_class' => TaxClass::class,
            'rate'      => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
