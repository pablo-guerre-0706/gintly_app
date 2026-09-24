<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\TaxClass;
use App\Observers\BusinessObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([BusinessObserver::class])]  // hook listo, se llena en MOD-05/MOD-11
class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',   // seguro aquí: businesses es la raíz, no hay fuga de tenant
        'plan',
        'status',
        'tax_rate',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'status'   => BusinessStatus::class,
            'tax_rate' => 'decimal:4',
        ];
    }

    /**
     * Tasa ESTÁNDAR GENERAL vigente del negocio, resuelta desde tax_rules (fuente
     * fiscal OPERATIVA única). Devuelve null si aún no hay regla (p. ej. durante el
     * aprovisionamiento). business.tax_rate es un ESPEJO de compatibilidad: se siembra
     * al crear el negocio y se sincroniza cuando se edita la tasa por el endpoint del
     * negocio (que además la TRADUCE a la regla); el valor autoritativo para mostrar y
     * calcular proviene de la regla, no de la columna.
     *
     * withoutGlobalScopes: no depende del contexto de tenant vigente.
     */
    public function standardTaxRate(): ?string
    {
        $rate = TaxRule::withoutGlobalScopes()
            ->where('business_id', $this->getKey())
            ->where('tax_class', TaxClass::Standard->value)
            ->whereNull('branch_id')
            ->where('is_active', true)
            ->value('rate');

        return $rate !== null ? (string) $rate : null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
