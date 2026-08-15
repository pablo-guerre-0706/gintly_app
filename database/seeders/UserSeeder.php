<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

final class UserSeeder extends Seeder
{
    // CS de usuarios de prueba (política: ≥12, letras+números+símbolo). Cambiar en producción.
    private const DEMO_PASSWORD = 'GintlyDev#2026';

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // 1) Negocio de prueba. Crearlo dispara el BusinessObserver (Consumidor Final,
        //    document_sequences y las 6 anomaly_rules del aprovisionamiento).
        $business = Business::firstOrCreate(
            ['slug' => 'gintly-demo'],
            [
                'name'     => 'Gintly Demo',
                'timezone' => 'America/Managua',
                'tax_rate' => '0.1500', // 15% (fracción, bcmath).
            ],
        );

        // 2) Este llamado: app(RolesAndPermissionsSeeder::class)->syncBusinessRoles($business->id);
        // que materializa ROL-01/02/03 del negocio lo eliminamos, porque ya queda integrado en
        // BusinessObserver.

        // 3) Usuario de sistema (transversal, team NULL) → ROL-SYS. Nunca huérfano.
        $system = $this->upsertUser('sistema@gintly.test', 'Sistema Gintly', $business->id);
        $registrar->setPermissionsTeamId(null);
        $system->syncRoles([RoleName::System->value]); // Exactamente un rol activo (regla de dominio).

        // 4) Usuarios del negocio de prueba, uno por rol (team = business_id).
        $registrar->setPermissionsTeamId($business->id);

        $this->upsertUser('propietario@gintly.test', 'Propietario Demo', $business->id)
            ->syncRoles([RoleName::Owner->value]);   // ROL-01

        $this->upsertUser('administrador@gintly.test', 'Administrador Demo', $business->id)
            ->syncRoles([RoleName::Admin->value]);    // ROL-02

        $this->upsertUser('operativo@gintly.test', 'Operativo Demo', $business->id)
            ->syncRoles([RoleName::Operator->value]); // ROL-03

        // 5) Restaura el contexto global tras el seeding.
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    // Crea o actualiza un usuario de forma idempotente, sin dejarlo nunca sin negocio ni contrasena
    private function upsertUser(string $email, string $name, int $businessId): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'business_id'       => $businessId, // NULL solo para el usuario de sistema.
                'password'          => self::DEMO_PASSWORD, // El cast 'hashed' del modelo lo cifra solo.
            ],
        );
    }
}

