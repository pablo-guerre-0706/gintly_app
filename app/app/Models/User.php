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

    /**
     * ¿La cuenta ostenta el rol de sistema (ROL-SYS) en CUALQUIER equipo?
     * Comprobación agnóstica del team (Spatie): ROL-SYS es un actor de procesos
     * automáticos y jamás debe iniciar sesión ni operar endpoints humanos, sin
     * importar bajo qué business se haya registrado la asignación.
     */
    public function hasSystemRole(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', $this->getMorphClass())
            ->where('mhr.model_id', $this->getKey())
            ->where('r.name', RoleName::System->value)
            ->exists();
    }

    public function operativeProfiles(): HasMany
    {
        return $this->hasMany(UserOperativeProfile::class);
    }

    /**
     * Perfiles operativos (valores string) del usuario. Solo aplican a ROL-03.
     * @return array<int, string>
     */
    public function profileValues(): array
    {
        return $this->operativeProfiles->pluck('profile')
            ->map(static fn ($p) => $p instanceof \App\Enums\OperativeProfile ? $p->value : (string) $p)
            ->all();
    }

    public function hasProfile(\App\Enums\OperativeProfile $profile): bool
    {
        return in_array($profile->value, $this->profileValues(), true);
    }

    /**
     * Compuerta FINA de ROL-03: ¿algún perfil del usuario habilita esta capacidad (permiso)?
     * Fuente única: config/profiles.php. Para ROL-01/ROL-02 la autoridad proviene del rol humano,
     * por lo que este método NO es su compuerta (devuelve true al no ser ROL-03 operativo).
     */
    public function operativeCan(string $permission): bool
    {
        foreach ($this->operativeProfiles as $assignment) {
            $profile = $assignment->profile;
            $profile = $profile instanceof \App\Enums\OperativeProfile
                ? $profile
                : \App\Enums\OperativeProfile::tryFrom((string) $profile);

            if ($profile !== null && $profile->grants($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isOperator(): bool
    {
        return $this->getRoleNames()->first() === RoleName::Operator->value;
    }

    /**
     * Capacidades EFECTIVAS de interfaz (Fase 6, para /me y dashboards). NO son autorización por
     * recurso (las Policies siguen siendo la autoridad). Para ROL-03 son la unión de las capacidades
     * de sus perfiles (config/profiles.php); para ROL-01/ROL-02 son los permisos de su rol (Spatie).
     * ROL-SYS no llega aquí (bloqueado por EnsureOperableUser).
     *
     * @return array<int, string>
     */
    public function effectiveCapabilities(): array
    {
        if ($this->isOperator()) {
            $caps = [];
            foreach ($this->operativeProfiles as $assignment) {
                $profile = $assignment->profile instanceof \App\Enums\OperativeProfile
                    ? $assignment->profile
                    : \App\Enums\OperativeProfile::tryFrom((string) $assignment->profile);

                if ($profile !== null) {
                    $caps = array_merge($caps, $profile->capabilities());
                }
            }

            sort($caps);

            return array_values(array_unique($caps));
        }

        // ROL-01 / ROL-02: permisos del rol humano bajo el equipo (negocio) vigente.
        return $this->getAllPermissions()->pluck('name')->sort()->values()->all();
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
