<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpiar la caché de permisos ANTES de sembrar.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Catálogo de permisos (mapéalos desde tu FRD).
        $permissions = [
            'ver dashboard',
            'gestionar perfil propio',
            // ...aquí van los permisos reales de gintly_app
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        // 3. Crear el rol y sincronizar sus permisos.
        $cajero1 = Role::firstOrCreate([
            'name'       => 'cajero1',
            'guard_name' => 'web',
        ]);

        $cajero1->syncPermissions([
            'ver dashboard',
            'gestionar perfil propio',
        ]);
    }
}
