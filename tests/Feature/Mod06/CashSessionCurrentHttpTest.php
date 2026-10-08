<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-06 · Fase 7 — GET /api/v1/cash-sessions/current.
 * Devuelve SOLO la sesión abierta del propio usuario; estable {data:null} si no hay; nunca la de otro.
 */
final class CashSessionCurrentHttpTest extends MysqlTestCase
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
            'name' => 'Neg '.(++self::$seq), 'slug' => 'neg-cs-'.self::$seq,
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);
        $branch = new Branch();
        $branch->forceFill(['business_id' => $business->id, 'name' => 'S1', 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        $register = new CashRegister();
        $register->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Caja '.self::$seq, 'is_active' => true])->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'branch', 'register');
    }

    private function makeUser(Business $b, ?Branch $branch = null): User
    {
        $u = new User(['name' => 'op'.(++self::$seq), 'email' => 'u'.self::$seq.'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'branch_id' => $branch?->id]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole(RoleName::Operator->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $u;
    }

    private function openSession(object $t, User $u): CashSession
    {
        $s = new CashSession();
        $s->forceFill([
            'business_id' => $t->business->id, 'cash_register_id' => $t->register->id,
            'opened_by' => $u->id, 'status' => 'abierta', 'opening_amount' => '0.00', 'opened_at' => now(),
        ])->saveQuietly();

        return $s->refresh();
    }

    public function test_current_devuelve_la_propia_sesion_abierta(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, $t->branch);
        $session = $this->openSession($t, $op);

        $this->asUser($op)->getJson('/api/v1/cash-sessions/current')
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);
    }

    public function test_current_estable_cuando_no_hay_sesion(): void
    {
        $t = $this->seedTenant();
        $op = $this->makeUser($t->business, $t->branch);

        $this->asUser($op)->getJson('/api/v1/cash-sessions/current')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_current_nunca_devuelve_la_sesion_de_otro_usuario(): void
    {
        $t = $this->seedTenant();
        $owner = $this->makeUser($t->business, $t->branch);
        $other = $this->makeUser($t->business, $t->branch);
        $this->openSession($t, $other); // sesión de OTRO usuario.

        // El usuario sin sesión propia no ve la ajena.
        $this->asUser($owner)->getJson('/api/v1/cash-sessions/current')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }
}
