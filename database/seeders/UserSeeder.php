<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class UserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'GintlyDev#2026';

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // 1) Negocio de prueba (dispara el BusinessObserver y sus seeders)
        $business = Business::firstOrCreate(
            ['slug' => 'gintly-demo'],
            [
                'name'     => 'Gintly Demo',
                'timezone' => 'America/Managua',
                'tax_rate' => '0.1500',
            ],
        );

        // Aseguramos que los roles del negocio estén sincronizados
        app(RolesAndPermissionsSeeder::class)->syncBusinessRoles($business->id);

        // 2) Establecemos el contexto del negocio para todas las asignaciones de roles
        $registrar->setPermissionsTeamId($business->id);

        // 3) Usuario de sistema (con team_id del negocio en la tabla pivote)
        $system = $this->upsertUser('sistema@gintly.test', 'Sistema Gintly', $business->id);
        $system->syncRoles([RoleName::System->value]);

        // 4) Usuarios del negocio de prueba (team = business_id).
        $this->upsertUser('propietario@gintly.test', 'Propietario Demo', $business->id)
            ->syncRoles([RoleName::Owner->value]);

        $this->upsertUser('administrador@gintly.test', 'Administrador Demo', $business->id)
            ->syncRoles([RoleName::Admin->value]);

        $this->upsertUser('operativo@gintly.test', 'Operativo Demo', $business->id)
            ->syncRoles([RoleName::Operator->value]);

        // 5) Restaura el contexto global tras el seeding.
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    private function upsertUser(string $email, string $name, int $businessId): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name'        => $name,
                'business_id' => $businessId,
                'password'    => self::DEMO_PASSWORD,
            ],
        );
    }
}