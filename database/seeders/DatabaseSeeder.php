<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Orquestador puro: solo define el ORDEN de siembra: 1) Permisos globales, ROL-SYS, matriz de roles.
     * 2) Negocio de prueba, sus roles de tenant y los usuarios (cada uno con su rol).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
        ]);
    }
}
