<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\Users\ProfileService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-01 · Fase 6 — Contrato canónico de GET /api/v1/me.
 * Identidad + rol humano garantizado + sucursal + perfiles + capacidades efectivas + negocio + estado.
 */
final class MeContractHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $r = app(PermissionRegistrar::class);
        $r->setPermissionsTeamId(null);
        $r->forgetCachedPermissions();
        $auth = User::query()->whereKey($u->getKey())->firstOrFail();
        if (! $auth instanceof AuthenticatableContract) {
            throw new \RuntimeException('no auth');
        }

        return $this->actingAs($auth, 'web');
    }

    private function seedTenant(): object
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(RolesAndPermissionsSeeder::class)->run();

        $business = Business::create([
            'name' => 'Neg '.(++self::$seq), 'slug' => 'neg-me-'.self::$seq,
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);
        $branch = new Branch();
        $branch->forceFill(['business_id' => $business->id, 'name' => 'S1', 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);

        return (object) compact('business', 'branch', 'owner', 'admin');
    }

    private function makeUser(Business $b, RoleName $role, ?Branch $branch = null): User
    {
        $u = new User(['name' => $role->value.self::$seq, 'email' => 'u'.(++self::$seq).'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'branch_id' => $branch?->id]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $u;
    }

    public function test_me_estructura_completa_rol01(): void
    {
        $t = $this->seedTenant();
        $res = $this->asUser($t->owner)->getJson('/api/v1/me')->assertOk();

        $res->assertJsonStructure(['data' => ['id', 'name', 'email', 'is_active', 'role', 'branch_id', 'profiles', 'capabilities', 'business' => ['id', 'name', 'timezone', 'status']]]);
        $res->assertJsonPath('data.role', 'ROL-01');
        $res->assertJsonPath('data.profiles', []);
        $res->assertJsonPath('data.business.id', $t->business->id);
        // ROL-01 posee las potestades críticas.
        $this->assertContains('facturas.anular', $res->json('data.capabilities'));
        $this->assertContains('ventas.crear', $res->json('data.capabilities'));
    }

    public function test_me_rol02_sin_potestades_criticas(): void
    {
        $t = $this->seedTenant();
        $res = $this->asUser($t->admin)->getJson('/api/v1/me')->assertOk();

        $res->assertJsonPath('data.role', 'ROL-02');
        $this->assertContains('ventas.crear', $res->json('data.capabilities'));
        $this->assertNotContains('facturas.anular', $res->json('data.capabilities')); // exclusiva de ROL-01.
        $this->assertNotContains('proveedores.aprobar', $res->json('data.capabilities'));
    }

    public function test_me_rol03_capacidades_por_perfil(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        app(ProfileService::class)->replace($op, ['cajero'], $t->admin);

        $res = $this->asUser($op)->getJson('/api/v1/me')->assertOk();

        $res->assertJsonPath('data.role', 'ROL-03');
        $res->assertJsonPath('data.branch_id', $t->branch->id);
        $this->assertSame(['cajero'], $res->json('data.profiles'));
        // Capacidades = las del perfil cajero; NO incluye capacidades de facturador.
        $caps = $res->json('data.capabilities');
        $this->assertContains('caja.abrir', $caps);
        $this->assertContains('cuentas_por_cobrar.abonar', $caps);
        $this->assertNotContains('ventas.crear', $caps);   // capacidad de facturador, no asignada.
        $this->assertNotContains('facturas.anular', $caps); // jamás para ROL-03.
    }

    public function test_me_rol03_multiperfil_es_union(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        app(ProfileService::class)->replace($op, ['cajero', 'facturador'], $t->admin);

        $caps = $this->asUser($op)->getJson('/api/v1/me')->assertOk()->json('data.capabilities');
        $this->assertContains('caja.abrir', $caps);      // cajero
        $this->assertContains('ventas.crear', $caps);    // facturador
        $this->assertContains('facturas.crear', $caps);  // facturador
    }
}
