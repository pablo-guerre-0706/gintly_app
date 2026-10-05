<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Enums\Currency;
use App\Enums\RoleName;
use App\Exceptions\ExchangeRateMissingException;
use App\Exceptions\ImmutableRecordException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Cash\ExchangeRateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-06 · Administración del tipo de cambio NIO/USD (ROL-01/ROL-02). Vigencias inmutables versionadas:
 * alta append-only, historial, resolución de la tasa vigente (snapshot), base NIO implícita (tasa 1),
 * rechazo de moneda base, aislamiento multi-negocio e inmutabilidad del historial.
 */
final class ExchangeRateHttpTest extends MysqlTestCase
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

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);
        $cajero = $this->makeUser($business, RoleName::Operator, $branch);

        return (object) compact('business', 'branch', 'owner', 'admin', 'cajero');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.self::$seq, 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
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

    private function seedRate(object $t, string $currency, string $rate, Carbon $effectiveFrom, ?User $by = null): ExchangeRate
    {
        $row = new ExchangeRate;
        $row->forceFill([
            'business_id' => $t->business->id,
            'currency' => $currency,
            'rate' => $rate,
            'effective_from' => $effectiveFrom,
            'created_by' => ($by ?? $t->admin)->id,
        ])->save();

        return $row->refresh();
    }

    // ---------------- Alta (ROL-01/ROL-02) ----------------

    public function test_admin_registra_tipo_de_cambio(): void
    {
        $t = $this->seedTenant('fx');

        $this->asUser($t->admin)->postJson('/api/v1/exchange-rates', [
            'currency' => 'USD', 'rate' => '36.5', 'effective_from' => now()->toDateTimeString(),
        ])->assertCreated()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.rate', '36.500000')
            ->assertJsonPath('data.created_by', $t->admin->id);

        $this->assertSame(1, ExchangeRate::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_owner_tambien_registra(): void
    {
        $t = $this->seedTenant('fx');

        $this->asUser($t->owner)->postJson('/api/v1/exchange-rates', [
            'currency' => 'USD', 'rate' => '37.1234', 'effective_from' => now()->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('data.rate', '37.123400');
    }

    public function test_cajero_no_puede_registrar(): void
    {
        $t = $this->seedTenant('fx');

        $this->asUser($t->cajero)->postJson('/api/v1/exchange-rates', [
            'currency' => 'USD', 'rate' => '36.5', 'effective_from' => now()->toDateTimeString(),
        ])->assertStatus(403);

        $this->assertSame(0, ExchangeRate::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    // ---------------- Validación ----------------

    public function test_moneda_base_nio_rechazada_422(): void
    {
        $t = $this->seedTenant('fx');

        $this->asUser($t->admin)->postJson('/api/v1/exchange-rates', [
            'currency' => 'NIO', 'rate' => '1', 'effective_from' => now()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['currency']);
    }

    public function test_tasa_no_positiva_rechazada_422(): void
    {
        $t = $this->seedTenant('fx');

        $this->asUser($t->admin)->postJson('/api/v1/exchange-rates', [
            'currency' => 'USD', 'rate' => '0', 'effective_from' => now()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['rate']);
    }

    public function test_demasiados_decimales_rechazado_422(): void
    {
        $t = $this->seedTenant('fx');

        $this->asUser($t->admin)->postJson('/api/v1/exchange-rates', [
            'currency' => 'USD', 'rate' => '36.1234567', 'effective_from' => now()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['rate']);
    }

    // ---------------- Historial / filtro / aislamiento ----------------

    public function test_historial_mas_reciente_primero_y_filtro_por_moneda(): void
    {
        $t = $this->seedTenant('fx');
        $this->seedRate($t, 'USD', '36.00', now()->subDays(2));
        $this->seedRate($t, 'USD', '37.00', now()->subDay());

        $data = $this->asUser($t->admin)->getJson('/api/v1/exchange-rates?currency=USD')->assertOk()->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('37.000000', $data[0]['rate']); // vigencia más reciente primero
        $this->assertSame('36.000000', $data[1]['rate']);
    }

    public function test_aislamiento_multinegocio_en_historial(): void
    {
        $a = $this->seedTenant('fxa');
        $b = $this->seedTenant('fxb');
        $this->seedRate($a, 'USD', '36.00', now()->subDay());
        $this->seedRate($b, 'USD', '40.00', now()->subDay());

        $data = $this->asUser($a->admin)->getJson('/api/v1/exchange-rates')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('36.000000', $data[0]['rate']); // nunca ve la tasa del otro negocio
    }

    // ---------------- Resolver (snapshot) ----------------

    public function test_resolver_base_nio_es_uno(): void
    {
        $t = $this->seedTenant('fx');
        $svc = app(ExchangeRateService::class);

        $this->assertSame('1', $svc->rateFor($t->business->id, Currency::Nio));
    }

    public function test_resolver_toma_la_vigencia_mas_reciente_no_futura(): void
    {
        $t = $this->seedTenant('fx');
        $this->seedRate($t, 'USD', '36.00', now()->subDays(2));
        $this->seedRate($t, 'USD', '37.00', now()->subDay());
        $this->seedRate($t, 'USD', '99.00', now()->addDay()); // futura: no debe elegirse hoy

        $rate = app(ExchangeRateService::class)->rateFor($t->business->id, Currency::Usd);
        $this->assertSame(0, bccomp($rate, '37.00', 6));
    }

    public function test_resolver_sin_tasa_lanza_excepcion(): void
    {
        $t = $this->seedTenant('fx');

        $this->expectException(ExchangeRateMissingException::class);
        app(ExchangeRateService::class)->rateFor($t->business->id, Currency::Usd);
    }

    // ---------------- Inmutabilidad ----------------

    public function test_tipo_de_cambio_es_inmutable(): void
    {
        $t = $this->seedTenant('fx');
        $row = $this->seedRate($t, 'USD', '36.00', now()->subDay());

        $this->expectException(ImmutableRecordException::class);
        $row->update(['rate' => '99.00']);
    }
}
