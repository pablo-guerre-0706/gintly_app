<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',       // el cast 'hashed' lo cifra al asignar
        'is_active',
        'branch_id',      // validar en FormRequest que sea del mismo tenant
        'last_login_at',
        // 'business_id' EXCLUIDO a propósito → se asigna explícito en el Service
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'is_active'     => 'boolean',
            'last_login_at' => 'datetime',
            // NADA de 'email_verified_at' → la columna no existe
        ];
    }

    // business() manual (no viene de trait, para no arrastrar el scope global)
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * ¿El usuario ostenta al menos el rol indicado (por nivel) en el negocio activo?
     * Resuelve el rol bajo el equipo de permisos vigente (SetPermissionsTeamId).
     * Base del alcance por rol (p. ej. visibilidad administrativa vs. propiedad ROL-03).
     */
    public function holdsAtLeast(RoleName $minimum): bool
    {
        $name = $this->getRoleNames()->first();
        $role = $name !== null ? RoleName::tryFrom((string) $name) : null;

        return $role?->atLeast($minimum) ?? false;
    }

    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_user_id');
    }

    public function ownedBusiness(): HasOne
    {
        return $this->hasOne(Business::class, 'owner_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
