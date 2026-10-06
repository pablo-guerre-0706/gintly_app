<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // 1) Limpieza de caché al inicio.
        $registrar->forgetCachedPermissions();

        // 2) Catálogo GLOBAL de permisos (team NULL).
        $registrar->setPermissionsTeamId(null);

        foreach ($this->allPermissions() as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    /**
     * Materializa los roles (incluyendo System) para un tenant concreto (team = business_id).
     */
    public function syncBusinessRoles(int $businessId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($businessId); // Contexto de equipo = negocio.

        Role::findOrCreate(RoleName::Owner->value, self::GUARD)
            ->syncPermissions($this->ownerPermissions());

        Role::findOrCreate(RoleName::Admin->value, self::GUARD)
            ->syncPermissions($this->adminPermissions());

        Role::findOrCreate(RoleName::Operator->value, self::GUARD)
            ->syncPermissions($this->operatorPermissions());

        // El rol System también se asocia al tenant para cumplir con el business_id NOT NULL
        Role::findOrCreate(RoleName::System->value, self::GUARD)
            ->syncPermissions($this->allPermissions());

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    private function permissionGroups(): array
    {
        return [
            'nucleo' => [
                'usuarios.ver', 'usuarios.gestionar',
                'sucursales.gestionar',
                'negocio.ver', 'negocio.actualizar',
                'auditoria.ver',
                'catalogo.ver', 'catalogo.gestionar',
            ],
            'inventario' => [
                'bodegas.ver', 'bodegas.gestionar',
                'inventario.ver', 'inventario.ajustar', 'inventario.conteo', 'inventario.traspaso',
            ],
            'compras' => [
                'proveedores.ver', 'proveedores.gestionar', 'proveedores.aprobar',
                'compras.ver', 'compras.crear', 'compras.recibir',
                'cuentas_por_pagar.ver', 'cuentas_por_pagar.pagar', 'cuentas_por_pagar.desbloquear',
            ],
            'clientes' => [
                'clientes.ver', 'clientes.gestionar',
                'clientes.credito.ver', 'clientes.credito.evaluar',
            ],
            'caja' => [
                'caja.gestionar',
                'caja.abrir', 'caja.cerrar',
                'caja.movimiento.crear', 'caja.movimiento.autorizar',
                'caja.reembolso',
            ],
            'ventas' => [
                'ventas.ver', 'ventas.crear',
                'facturas.ver', 'facturas.crear', 'facturas.anular', 'facturas.credito.autorizar',
            ],
            'cxc' => [
                'cuentas_por_cobrar.ver', 'cuentas_por_cobrar.abonar',
            ],
            'entregas' => [
                'entregas.ver', 'entregas.crear', 'entregas.revertir',
            ],
            'devoluciones' => [
                'devoluciones.ver', 'devoluciones.crear',
                'notas_credito.ver',
            ],
            'anomalias' => [
                'anomalias.ver', 'anomalias.justificar', 'anomalias.resolver',
                'reglas_anomalia.ver', 'reglas_anomalia.gestionar',
                'conciliacion.ver', 'conciliacion.ejecutar',
            ],
            'reporteria' => [
                'metas.ver', 'metas.gestionar',
                'kpis.ver', 'kpis.recalcular',
                'reportes.ver', 'panel.ver',
                'definiciones_reporte.gestionar',
            ],
        ];
    }

    private function allPermissions(): array
    {
        return array_values(array_unique(Arr::flatten($this->permissionGroups())));
    }

    private function ownerOnlyPermissions(): array
    {
        return [
            'proveedores.aprobar',
            'cuentas_por_pagar.desbloquear',
            'caja.reembolso',
            'facturas.anular',
            'facturas.credito.autorizar',
            'anomalias.resolver',
            'reglas_anomalia.gestionar',
            'metas.gestionar',
            'kpis.recalcular',
            'reportes.ver',
            'panel.ver',
        ];
    }

    private function ownerPermissions(): array
    {
        return $this->allPermissions();
    }

    private function adminPermissions(): array
    {
        return array_values(array_diff($this->allPermissions(), $this->ownerOnlyPermissions()));
    }

    private function operatorPermissions(): array
    {
        return [
            'catalogo.ver', 'bodegas.ver', 'inventario.ver', 'clientes.ver', 'proveedores.ver',
            'inventario.conteo',
            'compras.ver', 'compras.crear', 'compras.recibir', 'cuentas_por_pagar.ver',
            'ventas.ver', 'ventas.crear', 'facturas.ver', 'facturas.crear',
            'caja.abrir', 'caja.cerrar', 'caja.movimiento.crear',
            'cuentas_por_cobrar.ver', 'cuentas_por_cobrar.abonar', 'clientes.credito.evaluar',
            'entregas.ver', 'entregas.crear',
            'devoluciones.ver', 'devoluciones.crear', 'notas_credito.ver',
        ];
    }
}