<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Enums\OperativeProfile;
use App\Enums\RoleName;
use App\Models\Business;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-01 · Fase 4 — Consistencia Seeder ↔ perfiles ↔ Policies (anti-divergencia).
 *
 * Autoridad de autorización: las Policies por NIVEL DE ROL (hasAtLeast). El catálogo de permisos +
 * perfiles es el modelo de capacidades. Estas pruebas impiden que el modelo derive:
 *   - ROL-03 concede EXACTAMENTE la unión de capacidades de perfiles (fuente config/profiles.php);
 *   - toda capacidad de perfil existe en el catálogo global (sin nombres huérfanos);
 *   - ninguna capacidad de perfil es una potestad exclusiva de ROL-01;
 *   - las operaciones críticas de ROL-01 siguen siendo exclusivas del propietario.
 */
final class PermissionProfileConsistencyTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function seedBusiness(): Business
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(RolesAndPermissionsSeeder::class)->run();

        return Business::create([
            'name' => 'Neg '.(++self::$seq), 'slug' => 'neg-p4-'.self::$seq,
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]); // observer → syncBusinessRoles.
    }

    /** @return array<int,string> */
    private function rolePerms(int $businessId, RoleName $role): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($businessId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $r = Role::where('name', $role->value)->where('business_id', $businessId)->first();
        $this->assertNotNull($r, "Falta el rol {$role->value} del negocio.");

        return $r->permissions->pluck('name')->all();
    }

    public function test_rol03_concede_exactamente_la_union_de_perfiles(): void
    {
        $b = $this->seedBusiness();
        $operator = $this->rolePerms($b->id, RoleName::Operator);

        $this->assertEqualsCanonicalizing(OperativeProfile::allCapabilities(), $operator);
    }

    public function test_capacidades_de_perfil_existen_en_el_catalogo_global(): void
    {
        $b = $this->seedBusiness();
        $owner = $this->rolePerms($b->id, RoleName::Owner); // ROL-01 = catálogo completo.

        foreach (OperativeProfile::cases() as $profile) {
            foreach ($profile->capabilities() as $cap) {
                $this->assertContains($cap, $owner, "Capacidad de perfil sin permiso en el catálogo: {$cap} ({$profile->value}).");
            }
        }
    }

    public function test_ningun_perfil_concede_potestades_exclusivas_de_rol01(): void
    {
        $b = $this->seedBusiness();
        $ownerOnly = array_values(array_diff(
            $this->rolePerms($b->id, RoleName::Owner),
            $this->rolePerms($b->id, RoleName::Admin),
        ));

        $this->assertNotEmpty($ownerOnly);
        $union = OperativeProfile::allCapabilities();

        $this->assertSame([], array_values(array_intersect($union, $ownerOnly)),
            'Un perfil operativo concede una potestad exclusiva de ROL-01.');
    }

    public function test_operaciones_criticas_siguen_siendo_exclusivas_de_rol01(): void
    {
        $b = $this->seedBusiness();
        $ownerOnly = array_diff(
            $this->rolePerms($b->id, RoleName::Owner),
            $this->rolePerms($b->id, RoleName::Admin),
        );

        foreach ([
            'facturas.anular', 'proveedores.aprobar', 'anomalias.resolver',
            'cuentas_por_pagar.desbloquear', 'caja.reembolso', 'facturas.credito.autorizar',
            'reglas_anomalia.gestionar', 'metas.gestionar', 'kpis.recalcular',
        ] as $critical) {
            $this->assertContains($critical, $ownerOnly, "La potestad crítica {$critical} debe ser exclusiva de ROL-01.");
        }
    }
}
