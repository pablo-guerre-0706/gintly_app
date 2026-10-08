<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

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
 * MOD-01 · Escalación de privilegios y tratamiento de ROL-SYS (reconciliación transversal).
 *
 * Verifica la defensa en profundidad: ROL-SYS no es asignable a humanos ni puede iniciar sesión;
 * la regla de rango se aplica IGUAL en POST /users y PUT /users/{user}/role; nadie concede autoridad
 * superior a la suya; y el propietario/administrador conservan lo permitido.
 */
final class RoleEscalationHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $authenticated = User::query()->whereKey($u->getKey())->firstOrFail();
        if (! $authenticated instanceof AuthenticatableContract) {
            throw new \RuntimeException('El usuario recargado no implementa Authenticatable.');
        }

        return $this->actingAs($authenticated, 'web');
    }

    private function seedTenant(string $slug): object
    {
        $this->app['auth']->forgetGuards();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(RolesAndPermissionsSeeder::class)->run(); // catálogo global + rol global ROL-SYS.

        $business = Business::create([
            'name' => 'Negocio '.$slug, 'slug' => $slug.'-'.(++self::$seq),
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]); // El observer siembra ROL-01/02/03 del negocio.

        $branch = new Branch();
        $branch->forceFill([
            'business_id' => $business->id, 'name' => 'S1 '.$slug, 'address' => 'Dir', 'opened_at' => now()->toDateString(), 'is_active' => true,
        ])->saveQuietly();

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'branch', 'owner', 'admin');
    }

    private function makeUser(Business $business, RoleName $role, ?Branch $branch = null): User
    {
        $user = new User([
            'name' => $role->value.' '.(++self::$seq), 'email' => 'u'.self::$seq.'@test.local',
            'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'branch_id' => $branch?->id,
        ]);
        $user->business_id = $business->id;
        $user->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $user->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
    }

    /** @param array<string,mixed> $override */
    private function newUserPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Nuevo '.(++self::$seq),
            'email' => 'nuevo'.self::$seq.'@test.local',
            'password' => 'secret-Password-123',
            'password_confirmation' => 'secret-Password-123',
            'role' => 'ROL-03',
        ], $override);
    }

    // ---------------- Registro canónico ----------------

    public function test_rol_sys_no_esta_en_los_roles_asignables(): void
    {
        $this->assertNotContains('ROL-SYS', RoleName::assignableValues());
        $this->assertSame(['ROL-01', 'ROL-02', 'ROL-03'], RoleName::assignableValues());
        $this->assertSame([], RoleName::System->grantableValues());
        $this->assertSame(['ROL-02', 'ROL-03'], RoleName::Admin->grantableValues());
        $this->assertSame(['ROL-01', 'ROL-02', 'ROL-03'], RoleName::Owner->grantableValues());
    }

    // ---------------- Escalación en POST /users ----------------

    public function test_rol02_no_puede_crear_rol_sys(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->admin)->postJson('/api/v1/users', $this->newUserPayload(['role' => 'ROL-SYS']))
            ->assertStatus(422); // fuera del allowlist de roles humanos.
    }

    public function test_rol02_no_puede_crear_rol01(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->admin)->postJson('/api/v1/users', $this->newUserPayload(['role' => 'ROL-01']))
            ->assertStatus(403); // regla de rango (RoleAssignmentException).
    }

    public function test_rol01_no_puede_crear_rol_sys(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->owner)->postJson('/api/v1/users', $this->newUserPayload(['role' => 'ROL-SYS']))
            ->assertStatus(422);
    }

    public function test_rol01_puede_crear_rol02_y_rol03(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->owner)->postJson('/api/v1/users', $this->newUserPayload(['role' => 'ROL-02']))
            ->assertStatus(201)->assertJsonPath('data.role', 'ROL-02');
        $this->asUser($t->owner)->postJson('/api/v1/users', $this->newUserPayload(['role' => 'ROL-03', 'branch_id' => $t->branch->id, 'profiles' => ['cajero']]))
            ->assertStatus(201)->assertJsonPath('data.role', 'ROL-03');
    }

    public function test_rol02_puede_crear_rol03(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->admin)->postJson('/api/v1/users', $this->newUserPayload(['role' => 'ROL-03', 'branch_id' => $t->branch->id, 'profiles' => ['facturador']]))
            ->assertStatus(201)->assertJsonPath('data.role', 'ROL-03');
    }

    // ---------------- Escalación en PUT /users/{user}/role ----------------

    public function test_rol02_no_puede_asignar_rol01_via_put(): void
    {
        $t = $this->seedTenant('a');
        $target = $this->makeUser($t->business, RoleName::Operator, $t->branch);

        $this->asUser($t->admin)->putJson("/api/v1/users/{$target->id}/role", ['role' => 'ROL-01'])
            ->assertStatus(403); // Policy assignRole: antiescalada por proxy.
    }

    public function test_nadie_asigna_rol_sys_via_put(): void
    {
        $t = $this->seedTenant('a');
        $target = $this->makeUser($t->business, RoleName::Operator, $t->branch);

        $this->asUser($t->owner)->putJson("/api/v1/users/{$target->id}/role", ['role' => 'ROL-SYS'])
            ->assertStatus(403); // La Policy assignRole (authorize) bloquea ROL-SYS antes de validar.
    }

    public function test_rol01_puede_reasignar_rol02(): void
    {
        $t = $this->seedTenant('a');
        $target = $this->makeUser($t->business, RoleName::Operator, $t->branch);

        $this->asUser($t->owner)->putJson("/api/v1/users/{$target->id}/role", ['role' => 'ROL-02'])
            ->assertStatus(200)->assertJsonPath('data.role', 'ROL-02');
    }

    // ---------------- ROL-SYS no inicia sesión ----------------

    public function test_rol_sys_no_puede_iniciar_sesion(): void
    {
        $t = $this->seedTenant('a');

        // Cuenta ROL-SYS activa con contraseña conocida (peor caso: alguien la dejó utilizable).
        $sys = new User([
            'name' => 'Sistema', 'email' => 'sys'.(++self::$seq).'@test.local',
            'password' => Hash::make('secret-Password-123'), 'is_active' => true,
        ]);
        $sys->business_id = $t->business->id;
        $sys->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($t->business->id);
        $sys->assignRole(RoleName::System->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->postJson('/api/v1/auth/login', [
            'business_slug' => $t->business->slug,
            'email' => $sys->email,
            'password' => 'secret-Password-123',
        ])->assertStatus(401); // AuthService bloquea ROL-SYS.

        $this->assertTrue($sys->fresh()->hasSystemRole());
    }

    // ---------------- ROL-SYS / inactivo con sesión ya emitida ----------------

    private function makeSystemUser(Business $business): User
    {
        $sys = new User([
            'name' => 'Sistema', 'email' => 'sys'.(++self::$seq).'@test.local',
            'password' => Hash::make('secret-Password-123'), 'is_active' => true,
        ]);
        $sys->business_id = $business->id;
        $sys->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $sys->assignRole(RoleName::System->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $sys;
    }

    public function test_rol_sys_con_sesion_activa_no_accede_a_me(): void
    {
        $t = $this->seedTenant('a');
        $sys = $this->makeSystemUser($t->business);

        // actingAs simula una sesión ya emitida: aun así, cada petición se bloquea.
        $this->asUser($sys)->getJson('/api/v1/me')->assertStatus(403);
    }

    public function test_rol_sys_con_sesion_activa_no_accede_a_otro_endpoint_protegido(): void
    {
        $t = $this->seedTenant('a');
        $sys = $this->makeSystemUser($t->business);

        $this->asUser($sys)->getJson('/api/v1/users')->assertStatus(403);
        $this->asUser($sys)->getJson('/api/v1/business')->assertStatus(403);
    }

    public function test_usuario_inactivo_con_sesion_activa_es_bloqueado(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        $op->forceFill(['is_active' => false])->save();

        $this->asUser($op)->getJson('/api/v1/me')->assertStatus(403);
    }

    public function test_roles_humanos_activos_acceden_a_me(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);

        $this->asUser($t->owner)->getJson('/api/v1/me')->assertStatus(200)->assertJsonPath('data.email', $t->owner->email);
        $this->asUser($t->admin)->getJson('/api/v1/me')->assertStatus(200);
        $this->asUser($op)->getJson('/api/v1/me')->assertStatus(200);
    }

    // ---------------- Blindaje de sesión en rutas WEB autenticadas ----------------

    public function test_rol_sys_con_sesion_no_accede_a_ruta_web_y_sesion_se_invalida(): void
    {
        $this->withoutVite();
        $t = $this->seedTenant('a');
        $sys = $this->makeSystemUser($t->business);

        $this->asUser($sys)->get('/dashboard')->assertStatus(403);
        $this->assertGuest('web'); // la sesión quedó invalidada (logout del guard web).
    }

    public function test_inactivo_con_sesion_no_accede_a_ruta_web_y_sesion_se_invalida(): void
    {
        $this->withoutVite();
        $t = $this->seedTenant('a');
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);
        $op->forceFill(['is_active' => false])->save();

        $this->asUser($op)->get('/dashboard')->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_roles_humanos_activos_acceden_a_ruta_web(): void
    {
        $this->withoutVite();
        $t = $this->seedTenant('a');
        $op = $this->makeUser($t->business, RoleName::Operator, $t->branch);

        $this->asUser($t->owner)->get('/dashboard')->assertOk();
        $this->asUser($t->admin)->get('/dashboard')->assertOk();
        $this->asUser($op)->get('/dashboard')->assertOk();
    }

    public function test_rol_sys_fuera_de_la_jerarquia_humana(): void
    {
        // hasAtLeast/atLeast nunca considera ROL-SYS parte de la escala humana.
        $this->assertFalse(RoleName::System->atLeast(RoleName::Owner));
        $this->assertFalse(RoleName::System->atLeast(RoleName::Operator));
        $this->assertFalse(RoleName::Owner->atLeast(RoleName::System));
        $this->assertTrue(RoleName::Owner->atLeast(RoleName::Admin)); // control: la escala humana sigue intacta.
    }

    public function test_usuario_humano_si_puede_iniciar_sesion(): void
    {
        $t = $this->seedTenant('a');
        // control positivo: el propietario sí inicia sesión. Origin de dominio stateful (Sanctum SPA)
        // para que se inicie la sesión web y el controlador pueda regenerar el id.
        $this->postJson('/api/v1/auth/login', [
            'business_slug' => $t->business->slug,
            'email' => $t->owner->email,
            'password' => 'secret-Password-123',
        ], ['Origin' => rtrim((string) config('app.url'), '/')])
            ->assertStatus(200)->assertJsonPath('data.email', $t->owner->email);
    }
}
