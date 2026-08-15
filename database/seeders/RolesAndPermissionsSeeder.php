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
    /** Guard bajo el que operan Sanctum + Spatie. */
    private const GUARD = 'web';

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // 1) Limpieza de caché al inicio: evita permisos "fantasma" cacheados de corridas previas.
        $registrar->forgetCachedPermissions();

        // 2) Catálogo GLOBAL: los permisos no llevan business_id (team NULL).
        $registrar->setPermissionsTeamId(null);

        foreach ($this->allPermissions() as $permission) {
            Permission::findOrCreate($permission, self::GUARD); // Idempotente.
        }

        // 3) ROL-SYS: rol global (team NULL) con TODOS los permisos. Actor de procesos programados.
        $system = Role::findOrCreate(RoleName::System->value, self::GUARD);
        $system->syncPermissions($this->allPermissions());

        // Restaura el contexto global y refresca la caché con la matriz recién sembrada.
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    /**
     * Materializa los 3 roles para un tenant concreto (team = business_id).
     * Reutilizable: lo invoca el UserSeeder (pruebas) y debe invocarlo el BusinessObserver (producción),
     * para que todo negocio nazca con su matriz de autorización aislada.
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

        $registrar->setPermissionsTeamId(null); // Restaura el contexto global.
        $registrar->forgetCachedPermissions();
    }

    //  Catálogo de permisos por módulo (fuente única de verdad)
    
    /** @return array<string, array<int, string>> */
    private function permissionGroups(): array
    {
        return [
            // MOD-01 / MOD-02 · Núcleo y catálogo
            'nucleo' => [
                'usuarios.ver', 'usuarios.gestionar',
                'sucursales.gestionar',
                'negocio.ver', 'negocio.actualizar',
                'auditoria.ver',
                'catalogo.ver', 'catalogo.gestionar',
            ],
            // MOD-03 · Inventario y bodega
            'inventario' => [
                'bodegas.ver', 'bodegas.gestionar',
                'inventario.ver', 'inventario.ajustar', 'inventario.conteo', 'inventario.traspaso',
            ],
            // MOD-04 · Compras y proveedores
            'compras' => [
                'proveedores.ver', 'proveedores.gestionar', 'proveedores.aprobar',
                'compras.ver', 'compras.crear', 'compras.recibir',
                'cuentas_por_pagar.ver', 'cuentas_por_pagar.pagar', 'cuentas_por_pagar.desbloquear',
            ],
            // MOD-05 · Clientes
            'clientes' => [
                'clientes.ver', 'clientes.gestionar',
                'clientes.credito.ver', 'clientes.credito.evaluar',
            ],
            // MOD-06 · Caja
            'caja' => [
                'caja.gestionar',
                'caja.abrir', 'caja.cerrar',
                'caja.movimiento.crear', 'caja.movimiento.autorizar',
                'caja.reembolso',
            ],
            // MOD-07 · Ventas y facturación
            'ventas' => [
                'ventas.ver', 'ventas.crear',
                'facturas.ver', 'facturas.crear', 'facturas.anular', 'facturas.credito.autorizar',
            ],
            // MOD-08 · Cuentas por cobrar
            'cxc' => [
                'cuentas_por_cobrar.ver', 'cuentas_por_cobrar.abonar',
            ],
            // MOD-09 · Entregas y retiros
            'entregas' => [
                'entregas.ver', 'entregas.crear', 'entregas.revertir',
            ],
            // MOD-10 · Devoluciones y NC
            'devoluciones' => [
                'devoluciones.ver', 'devoluciones.crear',
                'notas_credito.ver',
            ],
            // MOD-11 · Conciliación y anomalías
            'anomalias' => [
                'anomalias.ver', 'anomalias.justificar', 'anomalias.resolver',
                'reglas_anomalia.ver', 'reglas_anomalia.gestionar',
                'conciliacion.ver', 'conciliacion.ejecutar',
            ],
            // MOD-12 · Reportería, KPIs e inteligencia de negocio
            'reporteria' => [
                'metas.ver', 'metas.gestionar',
                'kpis.ver', 'kpis.recalcular',
                'reportes.ver', 'panel.ver',
                'definiciones_reporte.gestionar',
            ],
        ];
    }

    /** @return array<int, string> */
    private function allPermissions(): array
    {
        return array_values(array_unique(Arr::flatten($this->permissionGroups())));
    }

    /**
     * Potestades exclusivas de ROL-01: decisiones críticas que ROL-02 nunca hereda.
     * @return array<int, string>
     */
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

    /** ROL-01 · Propietario/Dirección: autoridad plena del negocio. @return array<int, string> */
    private function ownerPermissions(): array
    {
        return $this->allPermissions();
    }

    /** ROL-02 · Administrador: gestión operativa, menos las de ROL-01. @return array<int, string> */
    private function adminPermissions(): array
    {
        return array_values(array_diff($this->allPermissions(), $this->ownerOnlyPermissions()));
    }

    /** ROL-03 · Operativo (compras, cajero, facturador, bodeguero, despachador). @return array<int, string> */
    private function operatorPermissions(): array
    {
        return [
            // Lectura de apoyo
            'catalogo.ver', 'bodegas.ver', 'inventario.ver', 'clientes.ver', 'proveedores.ver',
            // Bodega
            'inventario.conteo',
            // Compras y recepción
            'compras.ver', 'compras.crear', 'compras.recibir', 'cuentas_por_pagar.ver',
            // Punto de venta
            'ventas.ver', 'ventas.crear', 'facturas.ver', 'facturas.crear',
            // Caja
            'caja.abrir', 'caja.cerrar', 'caja.movimiento.crear',
            // Cobros y crédito
            'cuentas_por_cobrar.ver', 'cuentas_por_cobrar.abonar', 'clientes.credito.evaluar',
            // Entregas
            'entregas.ver', 'entregas.crear',
            // Devoluciones
            'devoluciones.ver', 'devoluciones.crear', 'notas_credito.ver',
        ];
    }
}
