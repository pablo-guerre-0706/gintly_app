<?php

declare(strict_types=1);

use App\Enums\OperativeProfile;
use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 4 · Reconcilia el rol ROL-03 de los negocios EXISTENTES con la unión exacta de capacidades
 * de los perfiles operativos (config/profiles.php). Los permisos son un modelo de capacidades (las
 * Policies autorizan por nivel de rol), por lo que esto NO altera autorizaciones vigentes; mantiene
 * consistente el catálogo por-negocio para /me y el gateo de perfiles. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sin infraestructura de roles todavía: nada que reconciliar.
        if (! DB::getSchemaBuilder()->hasTable('roles')) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $union = OperativeProfile::allCapabilities();

        DB::table('businesses')->pluck('id')->each(function ($businessId) use ($registrar, $union): void {
            $registrar->setPermissionsTeamId((int) $businessId);
            $registrar->forgetCachedPermissions();

            $role = \Spatie\Permission\Models\Role::where('name', RoleName::Operator->value)
                ->where('guard_name', 'web')
                ->where(fn ($q) => $q->where('business_id', $businessId)->orWhereNull('business_id'))
                ->first();

            $role?->syncPermissions($union); // permisos globales ya existen; idempotente.
        });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: la composición previa del rol operativo no se restablece automáticamente.
    }
};
