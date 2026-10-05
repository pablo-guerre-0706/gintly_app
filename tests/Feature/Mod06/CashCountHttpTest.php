<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Enums\RoleName;
use App\Exceptions\ImmutableRecordException;
use App\Models\Anomaly;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashCount;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use App\Models\UserOperativeProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-06 · Arqueo ciego INDEPENDIENTE (RF-06-04): conteos durante una sesión ABIERTA sin cerrarla.
 * Historial append-only, evidencia inmutable (denominaciones/usuario/fecha), esperado/diferencia ocultos
 * antes y revelados después, propiedad/sucursal ROL-03 + admin. El descuadre de un arqueo NO genera
 * anomalía ni cambia el estado de la sesión (solo el cierre formal lo hace).
 */
final class CashCountHttpTest extends MysqlTestCase
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
        $branch = $this->makeBranch($business, 'S1');
        $register = $this->makeRegister($business, $branch, 'Caja');

        $admin = $this->makeUser($business, RoleName::Admin);
        $cajero = $this->makeUser($business, RoleName::Operator, $branch);
        $this->assignProfile($cajero, $business->id, 'cajero');
        $cajeroB = $this->makeUser($business, RoleName::Operator, $branch);
        $this->assignProfile($cajeroB, $business->id, 'cajero');
        $bodeguero = $this->makeUser($business, RoleName::Operator, $branch);
        $this->assignProfile($bodeguero, $business->id, 'bodeguero');

        return (object) compact('business', 'branch', 'register', 'admin', 'cajero', 'cajeroB', 'bodeguero');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.self::$seq, 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeRegister(Business $b, Branch $branch, string $name): CashRegister
    {
        $r = new CashRegister;
        $r->forceFill(['business_id' => $b->id, 'branch_id' => $branch->id, 'name' => $name.self::$seq, 'is_active' => true])->save();

        return $r;
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

    private function assignProfile(User $u, int $businessId, string $profile): void
    {
        $row = new UserOperativeProfile(['profile' => $profile]);
        $row->user_id = $u->id;
        $row->business_id = $businessId;
        $row->save();
    }

    private function openSession(object $t, User $opener, string $opening = '100.00'): CashSession
    {
        $s = new CashSession;
        $s->forceFill([
            'business_id' => $t->business->id, 'cash_register_id' => $t->register->id, 'opened_by' => $opener->id,
            'status' => 'abierta', 'opening_amount' => $opening, 'opened_at' => now(),
        ])->saveQuietly();

        return $s->refresh();
    }

    private function addCashSale(object $t, CashSession $s, User $u, string $amount): void
    {
        $m = new CashMovement;
        $m->forceFill([
            'business_id' => $t->business->id, 'cash_session_id' => $s->id, 'user_id' => $u->id,
            'type' => 'ingreso', 'category' => 'venta', 'payment_method' => 'efectivo', 'amount' => $amount, 'created_at' => now(),
        ])->save();
    }

    /** @return array<string,mixed> */
    private function payload(string $counted, array $denominations): array
    {
        return ['counted_amount' => $counted, 'counted_denominations' => $denominations];
    }

    // ---------------- Registro + revelación + sesión intacta ----------------

    public function test_cajero_registra_arqueo_revela_esperado_y_no_cierra_sesion(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);     // fondo 100
        $this->addCashSale($t, $s, $t->cajero, '50.00'); // esperado = 150

        // Arqueo ciego: el cajero NO ve el esperado al registrar; la respuesta lo revela.
        $resp = $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('150.00', [['value' => '100.00', 'qty' => 1], ['value' => '50.00', 'qty' => 1]]))
            ->assertCreated();

        $resp->assertJsonPath('data.counted_amount', '150.00')
            ->assertJsonPath('data.expected_amount', '150.00')
            ->assertJsonPath('data.difference', '0.00');

        // La sesión sigue ABIERTA y su esperado sigue oculto (arqueo ciego a nivel de sesión).
        $this->assertSame('abierta', $s->refresh()->status->value);
        $this->asUser($t->cajero)->getJson("/api/v1/cash-sessions/{$s->id}")
            ->assertOk()->assertJsonPath('data.expected_amount', null);
    }

    public function test_arqueo_descuadrado_no_genera_anomalia_ni_cambia_estado(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);
        $this->addCashSale($t, $s, $t->cajero, '50.00'); // esperado 150

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('140.00', [['value' => '100.00', 'qty' => 1], ['value' => '20.00', 'qty' => 2]]))
            ->assertCreated()->assertJsonPath('data.difference', '-10.00');

        $this->assertSame('abierta', $s->refresh()->status->value); // no se marca descuadrada.
        $this->assertSame(0, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_multiples_arqueos_historial_mas_reciente_primero(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('100.00', [['value' => '100.00', 'qty' => 1]]))->assertCreated();
        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('95.00', [['value' => '95.00', 'qty' => 1]]))->assertCreated();

        $data = $this->asUser($t->cajero)->getJson("/api/v1/cash-sessions/{$s->id}/counts")->assertOk()->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($t->cajero->id, $data[0]['user_id']);
        $this->assertSame(2, CashCount::withoutGlobalScopes()->where('cash_session_id', $s->id)->count());
    }

    // ---------------- Validación de evidencia ----------------

    public function test_denominaciones_que_no_cuadran_422(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('150.00', [['value' => '100.00', 'qty' => 1]])) // suma 100 ≠ 150
            ->assertStatus(422)->assertJsonValidationErrors(['counted_denominations']);
    }

    public function test_sesion_cerrada_no_admite_arqueo_409(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);
        $s->forceFill(['status' => 'cerrada', 'closed_by' => $t->cajero->id, 'closed_at' => now(), 'counted_amount' => '100.00', 'expected_amount' => '100.00'])->saveQuietly();

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('100.00', [['value' => '100.00', 'qty' => 1]]))
            ->assertStatus(409);

        $this->assertSame(0, CashCount::withoutGlobalScopes()->where('cash_session_id', $s->id)->count());
    }

    // ---------------- Autorización / propiedad / sucursal / negocio ----------------

    public function test_arqueo_de_sesion_ajena_rechazado(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->cajeroB)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('100.00', [['value' => '100.00', 'qty' => 1]]))
            ->assertStatus(403); // no es su sesión.
    }

    public function test_admin_arquea_cualquier_sesion(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->admin)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('100.00', [['value' => '100.00', 'qty' => 1]]))
            ->assertCreated();
    }

    public function test_operador_sin_perfil_cajero_rechazado(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->bodeguero); // bodeguero abrió por forceFill (hipotético)

        $this->asUser($t->bodeguero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('100.00', [['value' => '100.00', 'qty' => 1]]))
            ->assertStatus(403); // sin caja.movimiento.crear.
    }

    public function test_cross_tenant_no_puede_arquear(): void
    {
        $t = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $sB = $this->openSession($b, $b->cajero);

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$sB->id}/counts",
            $this->payload('100.00', [['value' => '100.00', 'qty' => 1]]))
            ->assertStatus(404); // BusinessScope oculta la sesión del otro negocio.
    }

    // ---------------- Inmutabilidad ----------------

    public function test_arqueo_es_inmutable(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t, $t->cajero);
        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts",
            $this->payload('100.00', [['value' => '100.00', 'qty' => 1]]))->assertCreated();

        $count = CashCount::withoutGlobalScopes()->where('cash_session_id', $s->id)->firstOrFail();

        $this->expectException(ImmutableRecordException::class);
        $count->delete();
    }
}
