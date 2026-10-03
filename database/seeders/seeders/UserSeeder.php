<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OperativeProfile;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final class UserSeeder extends Seeder
{
    // CS de usuarios de prueba (política: ≥12, letras+números+símbolo). Cambiar en producción.
    private const DEMO_PASSWORD = 'GintlyDev#2026';

    public function run(): void
    {
        // SEGURIDAD: aprovisionamiento DEMO (negocio de prueba + cuentas con contraseña conocida).
        // NUNCA debe ejecutarse fuera de local/testing: desplegar cuentas demo activas en producción
        // (o staging) sería una puerta trasera. Allowlist de entornos, no blocklist de 'production'.
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('UserSeeder: aprovisionamiento demo omitido fuera de local/testing.');

            return;
        }

        $registrar = app(PermissionRegistrar::class);

        // 1) Negocio de prueba. Crearlo dispara el BusinessObserver (Consumidor Final,
        //    document_sequences y las 6 anomaly_rules del aprovisionamiento).
        $business = Business::firstOrCreate(
            ['slug' => 'gintly-demo'],
            [
                'name' => 'Gintly Demo',
                'timezone' => 'America/Managua',
                'tax_rate' => '0.1500', // 15% (fracción, bcmath).
            ],
        );

        // 2) Este llamado: app(RolesAndPermissionsSeeder::class)->syncBusinessRoles($business->id);
        // que materializa ROL-01/02/03 del negocio lo eliminamos, porque ya queda integrado en
        // BusinessObserver.

        // 3) Cuenta de sistema (ROL-SYS): actor de procesos automáticos, NO iniciable.
        // Se conserva para atribución/relación intermedia, pero NUNCA como cuenta humana utilizable:
        // is_active=false + contraseña aleatoria inutilizable. AuthService además rechaza el login de ROL-SYS.
        $system = User::updateOrCreate(
            ['email' => 'sistema@gintly.test'],
            [
                'name' => 'Sistema Gintly',
                'business_id' => $business->id,
                'password' => Str::random(64), // cast 'hashed' la cifra; no es adivinable ni reutilizada.
                'is_active' => false,           // no iniciable.
            ],
        );

        // Se pasa el business a Spatie para que model_has_roles no inserte team_id NULL.
        $registrar->setPermissionsTeamId($business->id);
        $system->syncRoles([RoleName::System->value]); // Exactamente un rol activo (regla de dominio).

        // 4) Usuarios del negocio de prueba, uno por rol (team = business_id).
        $registrar->setPermissionsTeamId($business->id);

        $owner = $this->upsertUser('propietario@gintly.test', 'Propietario Demo', $business->id);
        $owner->syncRoles([RoleName::Owner->value]);   // ROL-01

        $this->upsertUser('administrador@gintly.test', 'Administrador Demo', $business->id)
            ->syncRoles([RoleName::Admin->value]);    // ROL-02

        // 5) Sucursal y bodega del negocio demo (BusinessObserver no las crea). Idempotentes.
        //    Son el contexto mínimo para que un ROL-03 sea OPERABLE (sucursal + perfiles).
        $branch = $this->ensureBranch($business->id, 'Casa Matriz');
        $this->ensureWarehouse($business->id, $branch->id, 'Bodega Central');

        // 6) Usuarios ROL-03 OPERABLES: rol humano ROL-03 + sucursal + perfil(es). Sin esto quedan
        //    bloqueados (opción B). Uno por perfil + un multiperfil, para cubrir QA de los 4 perfiles.
        $this->operativeUser('operativo@gintly.test', 'Operativo Demo', $business->id, $branch->id, OperativeProfile::values(), $owner->id); // multiperfil
        $this->operativeUser('cajero@gintly.test', 'Cajero Demo', $business->id, $branch->id, [OperativeProfile::Cajero->value], $owner->id);
        $this->operativeUser('facturador@gintly.test', 'Facturador Demo', $business->id, $branch->id, [OperativeProfile::Facturador->value], $owner->id);
        $this->operativeUser('bodeguero@gintly.test', 'Bodeguero Demo', $business->id, $branch->id, [OperativeProfile::Bodeguero->value], $owner->id);
        $this->operativeUser('despachador@gintly.test', 'Despachador Demo', $business->id, $branch->id, [OperativeProfile::Despachador->value], $owner->id);

        // 7) Restaura el contexto global tras el seeding.
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    /** Sucursal del negocio demo (idempotente por negocio+nombre). */
    private function ensureBranch(int $businessId, string $name): Branch
    {
        $branch = Branch::query()->where('business_id', $businessId)->where('name', $name)->first();
        if ($branch !== null) {
            return $branch;
        }

        $branch = new Branch(['name' => $name, 'address' => 'Dirección demo', 'opened_at' => now()->toDateString(), 'is_active' => true]);
        $branch->business_id = $businessId; // BelongsToBusiness no tiene Auth en el seeder.
        $branch->save();

        return $branch;
    }

    /** Bodega predeterminada de la sucursal demo (idempotente por negocio+sucursal+nombre). */
    private function ensureWarehouse(int $businessId, int $branchId, string $name): Warehouse
    {
        $warehouse = Warehouse::query()->where('business_id', $businessId)->where('branch_id', $branchId)->where('name', $name)->first();
        if ($warehouse !== null) {
            return $warehouse;
        }

        $warehouse = new Warehouse(['branch_id' => $branchId, 'name' => $name, 'is_default' => true, 'is_active' => true]);
        $warehouse->business_id = $businessId;
        $warehouse->save();

        return $warehouse;
    }

    /**
     * Crea/actualiza un ROL-03 operable: sucursal asignada + perfiles persistidos (idempotente).
     *
     * @param  array<int,string>  $profiles
     */
    private function operativeUser(string $email, string $name, int $businessId, int $branchId, array $profiles, int $assignedBy): User
    {
        $user = $this->upsertUser($email, $name, $businessId);
        $user->branch_id = $branchId; // ROL-03 pertenece a UNA sucursal.
        $user->save();
        $user->syncRoles([RoleName::Operator->value]);

        foreach ($profiles as $profile) {
            $exists = UserOperativeProfile::query()
                ->where('user_id', $user->id)->where('profile', $profile)->exists();
            if ($exists) {
                continue; // idempotente: no duplica (unique user_id+profile).
            }

            $row = new UserOperativeProfile(['user_id' => $user->id, 'profile' => $profile, 'assigned_by' => $assignedBy]);
            $row->business_id = $businessId; // fuera de fillable; sin Auth en el seeder.
            $row->save();
        }

        return $user;
    }

    // Crea o actualiza un usuario de forma idempotente, sin dejarlo nunca sin negocio ni contrasena
    private function upsertUser(string $email, string $name, int $businessId): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'business_id' => $businessId, // NULL solo para el usuario de sistema.
                'password' => self::DEMO_PASSWORD, // El cast 'hashed' del modelo lo cifra solo.
            ],
        );
    }
}
