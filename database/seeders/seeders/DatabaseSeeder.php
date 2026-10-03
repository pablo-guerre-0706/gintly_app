<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Orquestador puro: define el ORDEN de siembra.
     * 1) RolesAndPermissionsSeeder: permisos globales, ROL-SYS y matriz de roles. Es infraestructura
     *    necesaria en CUALQUIER entorno (incluida producción).
     * 2) UserSeeder: aprovisionamiento DEMO (negocio de prueba + cuentas con contraseña conocida). Solo
     *    en local/testing. Doble barrera: también se auto-protege en UserSeeder::run() por si se invoca
     *    directamente (p. ej. `db:seed --class=UserSeeder`).
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(UserSeeder::class);
        }
    }
}
