<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Enums\OperativeProfile;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-01 · Fase 3 — Perfiles operativos de ROL-03 (persistentes, combinables, auditables).
 * Verifica catálogo, exigencia de sucursal+perfiles al crear/convertir, asignación, limpieza al
 * salir de ROL-03, opción B (preexistentes sin perfiles) y aislamiento de la administración.
 */
final class OperativeProfilesHttpTest extends MysqlTestCase
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
            'name' => 'Neg '.(++self::$seq), 'slug' => 'neg-'.self::$seq,
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

    /** @param array<string,mixed> $o */
    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'N'.(++self::$seq), 'email' => 'n'.self::$seq.'@t.local',
            'password' => 'secret-Password-123', 'password_confirmation' => 'secret-Password-123',
            'role' => 'ROL-03',
        ], $o);
    }

    // ---------------- Catálogo ----------------

    public function test_catalogo_de_perfiles_para_admin(): void
    {
        $t = $this->seedTenant();
        $res = $this->asUser($t->admin)->getJson('/api/v1/operative-profiles')->assertOk();
        $values = array_column($res->json('data'), 'value');
        sort($values);
        $this->assertSame(['bodeguero', 'cajero', 'despachador', 'facturador'], $values);
        // Cada perfil expone sus capacidades (permisos que habilita).
        $this->assertNotEmpty($res->json('data.0.capabilities'));
    }

    public function test_operador_no_ve_el_catalogo(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        $this->asUser($op)->getJson('/api/v1/operative-profiles')->assertStatus(403);
    }

    // ---------------- Crear ROL-03 exige sucursal + perfiles ----------------

    public function test_crear_rol03_sin_perfiles_falla(): void
    {
        $t = $this->seedTenant();
        $this->asUser($t->admin)->postJson('/api/v1/users', $this->payload(['branch_id' => $t->branch->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['profiles']);
    }

    public function test_crear_rol03_sin_sucursal_falla(): void
    {
        $t = $this->seedTenant();
        $this->asUser($t->admin)->postJson('/api/v1/users', $this->payload(['profiles' => ['cajero']]))
            ->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_crear_rol03_con_sucursal_y_perfiles_ok(): void
    {
        $t = $this->seedTenant();
        $res = $this->asUser($t->admin)->postJson('/api/v1/users', $this->payload([
            'branch_id' => $t->branch->id, 'profiles' => ['cajero', 'facturador'],
        ]))->assertStatus(201);

        $res->assertJsonPath('data.role', 'ROL-03');
        $this->assertEqualsCanonicalizing(['cajero', 'facturador'], $res->json('data.profiles'));
    }

    public function test_un_rol03_puede_combinar_varios_perfiles(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);

        $this->asUser($t->admin)->putJson("/api/v1/users/{$op->id}/profiles", ['profiles' => ['cajero', 'bodeguero', 'despachador']])
            ->assertStatus(200);

        $op->refresh()->load('operativeProfiles');
        $this->assertEqualsCanonicalizing(['cajero', 'bodeguero', 'despachador'], $op->profileValues());
    }

    // ---------------- Administración de perfiles ----------------

    public function test_asignar_perfiles_a_no_operativo_falla(): void
    {
        $t = $this->seedTenant();
        $admin2 = $this->makeUser($t->business, RoleName::Admin);

        // El objetivo es ROL-02: los perfiles solo aplican a ROL-03.
        $this->asUser($t->owner)->putJson("/api/v1/users/{$admin2->id}/profiles", ['profiles' => ['cajero']])
            ->assertStatus(403);
    }

    public function test_operador_no_puede_administrar_perfiles(): void
    {
        $t = $this->seedTenant();
        $op1 = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        $op2 = $this->makeUser($t->business, RoleName::Operator, $t->branch);

        $this->asUser($op1)->putJson("/api/v1/users/{$op2->id}/profiles", ['profiles' => ['cajero']])
            ->assertStatus(403);
    }

    public function test_perfiles_vacios_rechazados(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        $this->asUser($t->admin)->putJson("/api/v1/users/{$op->id}/profiles", ['profiles' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['profiles']);
    }

    // ---------------- Conversión de rol y limpieza ----------------

    public function test_convertir_a_rol03_exige_sucursal_y_perfiles(): void
    {
        $t = $this->seedTenant();
        $admin2 = $this->makeUser($t->business, RoleName::Admin);

        // Sin perfiles/sucursal → 422.
        $this->asUser($t->owner)->putJson("/api/v1/users/{$admin2->id}/role", ['role' => 'ROL-03'])
            ->assertStatus(422);

        // Con ambos → 200 y perfiles asignados.
        $this->asUser($t->owner)->putJson("/api/v1/users/{$admin2->id}/role", [
            'role' => 'ROL-03', 'branch_id' => $t->branch->id, 'profiles' => ['facturador'],
        ])->assertStatus(200);

        $this->assertSame(['facturador'], $admin2->fresh()->load('operativeProfiles')->profileValues());
    }

    public function test_salir_de_rol03_limpia_perfiles(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        $this->asUser($t->admin)->putJson("/api/v1/users/{$op->id}/profiles", ['profiles' => ['cajero']])->assertStatus(200);
        $this->assertNotEmpty($op->fresh()->load('operativeProfiles')->profileValues());

        // Convertir a ROL-02 → perfiles limpiados.
        $this->asUser($t->owner)->putJson("/api/v1/users/{$op->id}/role", ['role' => 'ROL-02'])->assertStatus(200);
        $this->assertSame([], $op->fresh()->load('operativeProfiles')->profileValues());
    }

    // ---------------- Opción B: ROL-03 preexistente sin perfiles ----------------

    public function test_rol03_preexistente_sin_perfiles_esta_bloqueado(): void
    {
        $t = $this->seedTenant();
        $legacy = $this->makeUser($t->business, RoleName::Operator, $t->branch); // sin perfiles.

        $legacy->load('operativeProfiles');
        $this->assertSame([], $legacy->profileValues());
        // Sin perfiles no habilita ninguna capacidad operativa (mínimo privilegio).
        $this->assertFalse($legacy->operativeCan('caja.abrir'));
        $this->assertFalse($legacy->operativeCan('facturas.crear'));
        $this->assertFalse($legacy->hasProfile(OperativeProfile::Cajero));
    }

    public function test_consulta_de_perfiles_por_usuario(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        $this->asUser($t->admin)->putJson("/api/v1/users/{$op->id}/profiles", ['profiles' => ['bodeguero']])->assertStatus(200);

        $this->asUser($t->admin)->getJson("/api/v1/users/{$op->id}/profiles")
            ->assertOk()->assertJsonPath('data.profiles', ['bodeguero']);
    }
}
