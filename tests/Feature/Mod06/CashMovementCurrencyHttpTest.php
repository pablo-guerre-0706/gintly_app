<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashRegister;
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
 * MOD-06 · Movimientos de caja con doble moneda NIO/USD (snapshot por operación). Cada movimiento
 * conserva moneda nativa, importe original, tasa congelada y equivalente NIO (base_amount generado).
 * NIO ⇒ tasa 1 implícita; USD sin tasa vigente ⇒ 422 controlado; compatibilidad histórica (sin moneda ⇒ NIO).
 */
final class CashMovementCurrencyHttpTest extends MysqlTestCase
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

    private function openSession(object $t, User $opener, string $opening = '100.00'): CashSession
    {
        $s = new CashSession;
        $s->forceFill([
            'business_id' => $t->business->id, 'cash_register_id' => $t->register->id, 'opened_by' => $opener->id,
            'status' => 'abierta', 'opening_amount' => $opening, 'opened_at' => now(),
        ])->saveQuietly();

        return $s->refresh();
    }

    private function seedRate(object $t, string $currency, string $rate): ExchangeRate
    {
        $row = new ExchangeRate;
        $row->forceFill([
            'business_id' => $t->business->id, 'currency' => $currency, 'rate' => $rate,
            'effective_from' => Carbon::now()->subDay(), 'created_by' => $t->admin->id,
        ])->save();

        return $row->refresh();
    }

    /** @return array<string,mixed> */
    private function movementPayload(CashSession $s, string $amount, ?string $currency = null): array
    {
        $p = ['cash_session_id' => $s->id, 'type' => 'ingreso', 'category' => 'ajuste', 'payment_method' => 'efectivo', 'amount' => $amount];
        if ($currency !== null) {
            $p['currency'] = $currency;
        }

        return $p;
    }

    // ---------------- NIO (compatibilidad) ----------------

    public function test_movimiento_sin_moneda_es_nio_tasa_uno(): void
    {
        $t = $this->seedTenant('m');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->admin)->postJson('/api/v1/cash-movements', $this->movementPayload($s, '50.00'))
            ->assertCreated()
            ->assertJsonPath('data.currency', 'NIO')
            ->assertJsonPath('data.exchange_rate', '1.000000')
            ->assertJsonPath('data.amount', '50.00')
            ->assertJsonPath('data.base_amount', '50.00');
    }

    public function test_movimiento_nio_explicito(): void
    {
        $t = $this->seedTenant('m');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->admin)->postJson('/api/v1/cash-movements', $this->movementPayload($s, '30.00', 'NIO'))
            ->assertCreated()
            ->assertJsonPath('data.currency', 'NIO')
            ->assertJsonPath('data.base_amount', '30.00');
    }

    // ---------------- USD (snapshot) ----------------

    public function test_movimiento_usd_congela_tasa_y_equivalente_nio(): void
    {
        $t = $this->seedTenant('m');
        $this->seedRate($t, 'USD', '36.50');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->admin)->postJson('/api/v1/cash-movements', $this->movementPayload($s, '10.00', 'USD'))
            ->assertCreated()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.exchange_rate', '36.500000')
            ->assertJsonPath('data.amount', '10.00')
            ->assertJsonPath('data.base_amount', '365.00'); // 10 USD × 36.5 = 365 NIO
    }

    public function test_movimiento_usd_sin_tasa_vigente_422_y_no_persiste(): void
    {
        $t = $this->seedTenant('m');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->admin)->postJson('/api/v1/cash-movements', $this->movementPayload($s, '10.00', 'USD'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'EXCHANGE_RATE_MISSING');

        $this->assertSame(0, CashMovement::withoutGlobalScopes()->where('cash_session_id', $s->id)->count());
    }

    public function test_moneda_invalida_422(): void
    {
        $t = $this->seedTenant('m');
        $s = $this->openSession($t, $t->cajero);

        $this->asUser($t->admin)->postJson('/api/v1/cash-movements', $this->movementPayload($s, '10.00', 'EUR'))
            ->assertStatus(422)->assertJsonValidationErrors(['currency']);
    }
}
