<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashRegisterAssignment;
use App\Models\CashSession;
use App\Models\User;
use App\Enums\RoleName;
use App\Models\UserOperativeProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-06 · Asignación Caja–Cajero (historial temporal) + filtro de historial por sucursal + aislamiento.
 * Una caja ≤ 1 cajero activo; un cajero ≤ 1 caja activa; misma sucursal; ROL-03 con perfil cajero; apertura
 * solo sobre caja asignada; reasignar = finalizar + asignar (historial append-only); conflictos y sesión abierta.
 */
final class CashRegisterAssignmentHttpTest extends MysqlTestCase
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

    private function seedTenant(string $slug): object
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(RolesAndPermissionsSeeder::class)->run();

        $business = Business::create([
            'name' => 'Neg '.$slug, 'slug' => $slug.'-'.(++self::$seq),
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);
        $branchA = $this->makeBranch($business, 'SA');
        $branchB = $this->makeBranch($business, 'SB');
        $registerA = $this->makeRegister($business, $branchA, 'CajaA');
        $registerB = $this->makeRegister($business, $branchB, 'CajaB');

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);
        $cajeroA = $this->makeUser($business, RoleName::Operator, $branchA, 'cajero');
        $cajeroA2 = $this->makeUser($business, RoleName::Operator, $branchA, 'cajero');
        $cajeroB = $this->makeUser($business, RoleName::Operator, $branchB, 'cajero');
        $bodeguero = $this->makeUser($business, RoleName::Operator, $branchA, 'bodeguero');

        return (object) compact('business', 'branchA', 'branchB', 'registerA', 'registerB', 'owner', 'admin', 'cajeroA', 'cajeroA2', 'cajeroB', 'bodeguero');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.(++self::$seq), 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeRegister(Business $b, Branch $branch, string $name): CashRegister
    {
        $r = new CashRegister;
        $r->forceFill(['business_id' => $b->id, 'branch_id' => $branch->id, 'name' => $name.(++self::$seq), 'is_active' => true])->save();

        return $r;
    }

    private function makeUser(Business $b, RoleName $role, ?Branch $branch = null, ?string $profile = null): User
    {
        $u = new User(['name' => $role->value.self::$seq, 'email' => 'u'.(++self::$seq).'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'branch_id' => $branch?->id]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        if ($profile !== null) {
            $row = new UserOperativeProfile(['profile' => $profile]);
            $row->user_id = $u->id;
            $row->business_id = $b->id;
            $row->save();
        }

        return $u;
    }

    private function seedAssignment(object $t, CashRegister $register, User $cashier): CashRegisterAssignment
    {
        $a = new CashRegisterAssignment;
        $a->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $register->branch_id, 'cash_register_id' => $register->id,
            'user_id' => $cashier->id, 'assigned_by' => $t->admin->id, 'assigned_at' => now(),
        ])->save();

        return $a->refresh();
    }

    private function openSessionRow(object $t, CashRegister $register, User $opener): CashSession
    {
        $s = new CashSession;
        $s->forceFill([
            'business_id' => $t->business->id, 'cash_register_id' => $register->id, 'opened_by' => $opener->id,
            'status' => 'abierta', 'opening_amount' => '0.00', 'opened_at' => now(),
        ])->saveQuietly();

        return $s->refresh();
    }

    private function assign(object $t, User $actor, CashRegister $register, User $cashier)
    {
        return $this->asUser($actor)->postJson('/api/v1/cash-register-assignments', [
            'cash_register_id' => $register->id, 'user_id' => $cashier->id,
        ]);
    }

    // ---------------- Alta / autorización ----------------

    public function test_admin_asigna_caja_a_cajero(): void
    {
        $t = $this->seedTenant('a');

        $this->assign($t, $t->admin, $t->registerA, $t->cajeroA)
            ->assertCreated()
            ->assertJsonPath('data.cash_register_id', $t->registerA->id)
            ->assertJsonPath('data.user_id', $t->cajeroA->id)
            ->assertJsonPath('data.active', true);

        $this->assertSame(1, CashRegisterAssignment::withoutGlobalScopes()->where('cash_register_id', $t->registerA->id)->whereNull('ended_at')->count());
    }

    public function test_owner_tambien_asigna(): void
    {
        $t = $this->seedTenant('a');
        $this->assign($t, $t->owner, $t->registerA, $t->cajeroA)->assertCreated();
    }

    public function test_cajero_no_puede_asignar(): void
    {
        $t = $this->seedTenant('a');
        $this->assign($t, $t->cajeroA, $t->registerA, $t->cajeroA2)->assertStatus(403);
    }

    // ---------------- Invariantes de dominio ----------------

    public function test_usuario_no_cajero_rechazado_422(): void
    {
        $t = $this->seedTenant('a');
        // bodeguero (ROL-03 sin perfil cajero).
        $this->assign($t, $t->admin, $t->registerA, $t->bodeguero)->assertStatus(422)->assertJsonValidationErrors(['user_id']);
        // ROL-02 no es cajero asignable.
        $this->assign($t, $t->admin, $t->registerA, $t->admin)->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    public function test_misma_sucursal_obligatoria_422(): void
    {
        $t = $this->seedTenant('a');
        // cajeroA (sucursal A) a registerB (sucursal B) → 422.
        $this->assign($t, $t->admin, $t->registerB, $t->cajeroA)->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    // ---------------- Conflictos ----------------

    public function test_caja_ocupada_devuelve_409(): void
    {
        $t = $this->seedTenant('a');
        $this->seedAssignment($t, $t->registerA, $t->cajeroA);

        $this->assign($t, $t->admin, $t->registerA, $t->cajeroA2)
            ->assertStatus(409)->assertJsonPath('error', 'CASH_REGISTER_ALREADY_ASSIGNED');
    }

    public function test_cajero_ya_asignado_devuelve_409(): void
    {
        $t = $this->seedTenant('a');
        $registerA2 = $this->makeRegister($t->business, $t->branchA, 'CajaA2');
        $this->seedAssignment($t, $t->registerA, $t->cajeroA);

        $this->assign($t, $t->admin, $registerA2, $t->cajeroA)
            ->assertStatus(409)->assertJsonPath('error', 'CASHIER_ALREADY_ASSIGNED');
    }

    public function test_no_asignar_con_sesion_abierta_vinculada(): void
    {
        $t = $this->seedTenant('a');
        // Caja con sesión abierta (sin asignación activa aún).
        $this->openSessionRow($t, $t->registerA, $t->cajeroA);

        $this->assign($t, $t->admin, $t->registerA, $t->cajeroA)
            ->assertStatus(409)->assertJsonPath('error', 'CASH_ASSIGNMENT_OPEN_SESSION');
    }

    // ---------------- Finalización y reasignación (historial) ----------------

    public function test_finalizar_y_reasignar_conserva_historial(): void
    {
        $t = $this->seedTenant('a');
        $first = $this->seedAssignment($t, $t->registerA, $t->cajeroA);

        // Finalizar.
        $this->asUser($t->admin)->deleteJson("/api/v1/cash-register-assignments/{$first->id}")
            ->assertOk()->assertJsonPath('data.active', false);

        // Reasignar la MISMA caja a otro cajero → nueva fila activa.
        $this->assign($t, $t->admin, $t->registerA, $t->cajeroA2)->assertCreated();

        // Historial append-only: la primera fila sigue existiendo (finalizada), la segunda activa.
        $this->assertSame(2, CashRegisterAssignment::withoutGlobalScopes()->where('cash_register_id', $t->registerA->id)->count());
        $this->assertNotNull($first->refresh()->ended_at); // la vieja conserva su cajero/caja, solo cerró vigencia.
        $this->assertSame($t->cajeroA->id, (int) $first->user_id);
    }

    public function test_finalizar_con_sesion_abierta_devuelve_409(): void
    {
        $t = $this->seedTenant('a');
        $a = $this->seedAssignment($t, $t->registerA, $t->cajeroA);
        $this->openSessionRow($t, $t->registerA, $t->cajeroA);

        $this->asUser($t->admin)->deleteJson("/api/v1/cash-register-assignments/{$a->id}")
            ->assertStatus(409)->assertJsonPath('error', 'CASH_ASSIGNMENT_OPEN_SESSION');

        $this->assertNull($a->refresh()->ended_at); // no se finalizó.
    }

    // ---------------- Apertura solo sobre caja asignada (Service-level) ----------------

    public function test_apertura_solo_sobre_caja_asignada(): void
    {
        $t = $this->seedTenant('a');

        // Sin asignación → 403 al abrir.
        $this->asUser($t->cajeroA)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->registerA->id, 'opening_amount' => '0.00',
        ])->assertStatus(403);
        $this->assertSame(0, CashSession::withoutGlobalScopes()->where('cash_register_id', $t->registerA->id)->count());

        // Con asignación → 201.
        $this->seedAssignment($t, $t->registerA, $t->cajeroA);
        $this->asUser($t->cajeroA)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->registerA->id, 'opening_amount' => '0.00',
        ])->assertCreated();
    }

    public function test_cajero_no_abre_caja_asignada_a_otro(): void
    {
        $t = $this->seedTenant('a');
        $registerA2 = $this->makeRegister($t->business, $t->branchA, 'CajaA2');
        $this->seedAssignment($t, $t->registerA, $t->cajeroA);   // cajeroA ← registerA
        $this->seedAssignment($t, $registerA2, $t->cajeroA2);     // cajeroA2 ← registerA2 (misma sucursal)

        // cajeroA intenta abrir registerA2 (asignada a cajeroA2, misma sucursal) → 403 (no es suya).
        $this->asUser($t->cajeroA)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $registerA2->id, 'opening_amount' => '0.00',
        ])->assertStatus(403);
    }

    // ---------------- Listado / alcance ROL-03 ----------------

    public function test_rol03_lista_solo_su_asignacion(): void
    {
        $t = $this->seedTenant('a');
        $this->seedAssignment($t, $t->registerA, $t->cajeroA);
        $registerA2 = $this->makeRegister($t->business, $t->branchA, 'CajaA2');
        $this->seedAssignment($t, $registerA2, $t->cajeroA2);

        $data = $this->asUser($t->cajeroA)->getJson('/api/v1/cash-register-assignments')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($t->cajeroA->id, $data[0]['user_id']);

        // El filtro user_id NO amplía el alcance del ROL-03.
        $spoof = $this->asUser($t->cajeroA)->getJson("/api/v1/cash-register-assignments?user_id={$t->cajeroA2->id}")->assertOk()->json('data');
        $this->assertCount(1, $spoof);
        $this->assertSame($t->cajeroA->id, $spoof[0]['user_id']);

        // ROL-02 ve ambas.
        $this->assertCount(2, $this->asUser($t->admin)->getJson('/api/v1/cash-register-assignments')->assertOk()->json('data'));
    }

    // ---------------- Aislamiento multinegocio ----------------

    public function test_finalizar_asignacion_de_otro_negocio_404(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $assignment = $this->seedAssignment($a, $a->registerA, $a->cajeroA);

        $this->asUser($b->admin)->deleteJson("/api/v1/cash-register-assignments/{$assignment->id}")->assertNotFound();
        $this->assertNull($assignment->refresh()->ended_at);
    }

    // ---------------- Bloqueo de desactivar/eliminar caja con sesión abierta ----------------

    public function test_no_desactivar_ni_eliminar_caja_con_sesion_abierta(): void
    {
        $t = $this->seedTenant('a');
        $this->openSessionRow($t, $t->registerA, $t->cajeroA);

        $this->asUser($t->admin)->putJson("/api/v1/cash-registers/{$t->registerA->id}", ['is_active' => false])
            ->assertStatus(409)->assertJsonPath('error', 'CASH_REGISTER_OPEN_SESSION');

        $this->asUser($t->admin)->deleteJson("/api/v1/cash-registers/{$t->registerA->id}")
            ->assertStatus(409)->assertJsonPath('error', 'CASH_REGISTER_OPEN_SESSION');

        $this->assertDatabaseHas('cash_registers', ['id' => $t->registerA->id, 'is_active' => 1, 'deleted_at' => null]);
    }

    // ---------------- Feature 2: filtro branch_id del historial ----------------

    public function test_historial_filtra_por_branch_id_admin_y_rol03(): void
    {
        $t = $this->seedTenant('a');
        $sA = $this->openSessionRow($t, $t->registerA, $t->cajeroA);
        $sB = $this->openSessionRow($t, $t->registerB, $t->cajeroB);

        // ROL-02 filtra por sucursal A → solo sesiones de cajas de A.
        $idsA = collect($this->asUser($t->admin)->getJson("/api/v1/cash-sessions?branch_id={$t->branchA->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($sA->id, $idsA);
        $this->assertNotContains($sB->id, $idsA);

        // ROL-02 filtra por sucursal B → solo B.
        $idsB = collect($this->asUser($t->admin)->getJson("/api/v1/cash-sessions?branch_id={$t->branchB->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$sB->id], $idsB);

        // ROL-03 (cajeroA) con branch_id de su sucursal → solo su sesión; nunca ve la de B.
        $own = collect($this->asUser($t->cajeroA)->getJson("/api/v1/cash-sessions?branch_id={$t->branchA->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$sA->id], $own);

        // ROL-03 con branch_id ajeno del MISMO negocio → no filtra datos de otros (vacío), sin fuga.
        $foreign = collect($this->asUser($t->cajeroA)->getJson("/api/v1/cash-sessions?branch_id={$t->branchB->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([], $foreign);
    }

    public function test_historial_branch_de_otro_negocio_404(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');

        // Una sucursal de OTRO negocio → 404, sin filtrar datos.
        $this->asUser($a->admin)->getJson("/api/v1/cash-sessions?branch_id={$b->branchA->id}")->assertNotFound();
    }
}
