<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Enums\RoleName;
use App\Models\Anomaly;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterAssignment;
use App\Models\CashSession;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Models\UserOperativeProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-06 · Sesión de caja con doble moneda NIO/USD: fondo dual, arqueo y cierre con esperado/contado/
 * diferencia SEPARADOS por moneda, y consolidado NIO INFORMATIVO. Invariante clave: una diferencia en NIO
 * O en USD marca 'descuadrada' y dispara anomalía, AUNQUE el consolidado NIO coincida.
 */
final class CashSessionDualCurrencyHttpTest extends MysqlTestCase
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

        // RF-06 asignación Caja–Cajero: el cajero queda asignado a la caja para poder abrirla por endpoint.
        $assignment = new CashRegisterAssignment();
        $assignment->forceFill([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'cash_register_id' => $register->id,
            'user_id' => $cajero->id, 'assigned_by' => $admin->id, 'assigned_at' => now(),
        ])->save();

        return (object) compact('business', 'branch', 'register', 'admin', 'cajero');
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

    private function seedRate(object $t, string $currency, string $rate): void
    {
        $row = new ExchangeRate;
        $row->forceFill([
            'business_id' => $t->business->id, 'currency' => $currency, 'rate' => $rate,
            'effective_from' => Carbon::now()->subDay(), 'created_by' => $t->admin->id,
        ])->save();
    }

    private function openSession(object $t, User $opener, string $nio = '100.00', string $usd = '0.00'): CashSession
    {
        $s = new CashSession;
        $s->forceFill([
            'business_id' => $t->business->id, 'cash_register_id' => $t->register->id, 'opened_by' => $opener->id,
            'status' => 'abierta', 'opening_amount' => $nio, 'opening_amount_usd' => $usd, 'opened_at' => now(),
        ])->saveQuietly();

        return $s->refresh();
    }

    private function addCashSale(object $t, CashSession $s, User $u, string $amount, string $currency = 'NIO', string $rate = '1'): void
    {
        $m = new CashMovement;
        $m->forceFill([
            'business_id' => $t->business->id, 'cash_session_id' => $s->id, 'user_id' => $u->id,
            'type' => 'ingreso', 'category' => 'venta', 'payment_method' => 'efectivo',
            'amount' => $amount, 'currency' => $currency, 'exchange_rate' => $rate, 'created_at' => now(),
        ])->save();
    }

    // ---------------- Apertura dual ----------------

    public function test_apertura_con_fondo_dual(): void
    {
        $t = $this->seedTenant('d');

        $resp = $this->asUser($t->cajero)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->register->id, 'opening_amount' => '100.00', 'opening_amount_usd' => '50.00',
        ])->assertCreated();

        $resp->assertJsonPath('data.opening_amount', '100.00')
            ->assertJsonPath('data.opening_amount_usd', '50.00');
    }

    public function test_apertura_sin_usd_es_cero(): void
    {
        $t = $this->seedTenant('d');

        $this->asUser($t->cajero)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->register->id, 'opening_amount' => '100.00',
        ])->assertCreated()->assertJsonPath('data.opening_amount_usd', '0.00');
    }

    // ---------------- Arqueo por moneda ----------------

    public function test_arqueo_revela_esperado_y_diferencia_por_moneda(): void
    {
        $t = $this->seedTenant('d');
        $s = $this->openSession($t, $t->cajero, '100.00', '20.00'); // fondo 100 NIO + 20 USD
        $this->addCashSale($t, $s, $t->cajero, '50.00');                         // +50 NIO  → esperado NIO 150
        $this->addCashSale($t, $s, $t->cajero, '10.00', 'USD', '36.50');         // +10 USD  → esperado USD 30

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/counts", [
            'counted_amount' => '150.00',
            'counted_denominations' => [['value' => '150.00', 'qty' => 1]],
            'counted_amount_usd' => '30.00',
            'counted_denominations_usd' => [['value' => '30.00', 'qty' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.expected_amount', '150.00')
            ->assertJsonPath('data.difference', '0.00')
            ->assertJsonPath('data.expected_amount_usd', '30.00')
            ->assertJsonPath('data.difference_usd', '0.00');
    }

    // ---------------- Cierre limpio dual ----------------

    public function test_cierre_limpio_ambas_monedas(): void
    {
        $t = $this->seedTenant('d');
        $this->seedRate($t, 'USD', '36.50');
        $s = $this->openSession($t, $t->cajero, '100.00', '20.00');
        $this->addCashSale($t, $s, $t->cajero, '10.00', 'USD', '36.50'); // esperado USD 30

        $resp = $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/close", [
            'counted_amount' => '100.00',
            'counted_denominations' => [['value' => '100.00', 'qty' => 1]],
            'counted_amount_usd' => '30.00',
            'counted_denominations_usd' => [['value' => '30.00', 'qty' => 1]],
        ])->assertOk();

        $resp->assertJsonPath('data.status', 'cerrada')
            ->assertJsonPath('data.difference', '0.00')
            ->assertJsonPath('data.difference_usd', '0.00')
            ->assertJsonPath('data.consolidated_nio.difference', '0.00');

        $this->assertSame('cerrada', $s->refresh()->status->value);
        $this->assertSame(0, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    // ---------------- Descuadre solo NIO ----------------

    public function test_descuadre_solo_nio_marca_descuadrada_y_anomalia(): void
    {
        $t = $this->seedTenant('d');
        $s = $this->openSession($t, $t->cajero, '100.00', '0.00');

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/close", [
            'counted_amount' => '90.00', // falta 10 NIO
            'counted_denominations' => [['value' => '90.00', 'qty' => 1]],
        ])->assertStatus(422);

        $this->assertSame('descuadrada', $s->refresh()->status->value);
        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    // ---------------- Descuadre solo USD (NIO cuadra) ----------------

    public function test_descuadre_solo_usd_marca_descuadrada_aunque_nio_cuadre(): void
    {
        $t = $this->seedTenant('d');
        $this->seedRate($t, 'USD', '36.50');
        $s = $this->openSession($t, $t->cajero, '100.00', '0.00');
        $this->addCashSale($t, $s, $t->cajero, '10.00', 'USD', '36.50'); // esperado USD 10

        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/close", [
            'counted_amount' => '100.00', // NIO cuadra
            'counted_denominations' => [['value' => '100.00', 'qty' => 1]],
            'counted_amount_usd' => '8.00', // faltan 2 USD
            'counted_denominations_usd' => [['value' => '8.00', 'qty' => 1]],
        ])->assertStatus(422);

        $fresh = $s->refresh();
        $this->assertSame('descuadrada', $fresh->status->value);
        $this->assertSame(0, bccomp((string) $fresh->difference, '0', 2));          // NIO cuadra
        $this->assertSame(-1, bccomp((string) $fresh->difference_usd, '0', 2));     // USD falta
        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    // ---------------- Consolidado NIO NUNCA oculta un descuadre por moneda ----------------

    public function test_consolidado_cuadra_pero_sigue_descuadrada_por_moneda(): void
    {
        $t = $this->seedTenant('d');
        $this->seedRate($t, 'USD', '10.00'); // tasa de referencia del consolidado
        $s = $this->openSession($t, $t->cajero, '100.00', '0.00');
        $this->addCashSale($t, $s, $t->cajero, '10.00', 'USD', '10.00'); // esperado USD 10

        // Sobra 10 NIO y falta 1 USD (= 10 NIO a la tasa 10): el consolidado NETEA a 0, pero cada
        // moneda descuadra → la sesión DEBE quedar descuadrada (el consolidado no puede ocultarlo).
        $this->asUser($t->cajero)->postJson("/api/v1/cash-sessions/{$s->id}/close", [
            'counted_amount' => '110.00', // +10 NIO
            'counted_denominations' => [['value' => '110.00', 'qty' => 1]],
            'counted_amount_usd' => '9.00', // -1 USD
            'counted_denominations_usd' => [['value' => '9.00', 'qty' => 1]],
        ])->assertStatus(422);

        $fresh = $s->refresh();
        $this->assertSame('descuadrada', $fresh->status->value);
        $this->assertSame(1, bccomp((string) $fresh->difference, '0', 2));       // +10 NIO
        $this->assertSame(-1, bccomp((string) $fresh->difference_usd, '0', 2));  // -1 USD

        // Consolidado informativo = 0 (10 NIO − 1 USD×10) y, aun así, la sesión está descuadrada.
        $this->asUser($t->admin)->getJson("/api/v1/cash-sessions/{$s->id}")
            ->assertOk()
            ->assertJsonPath('data.consolidated_nio.difference', '0.00')
            ->assertJsonPath('data.status', 'descuadrada');
    }
}
