<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Enums\RoleName;
use App\Models\AccountReceivable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserOperativeProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-01 · Fase 7 — CxC cobrables operativas + dashboards ROL-02/ROL-03 (datos reales).
 */
final class OperativeDashboardsHttpTest extends MysqlTestCase
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
            'name' => 'Neg '.(++self::$seq), 'slug' => 'neg-d-'.self::$seq,
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);
        $branch  = $this->makeBranch($business, 'S1');
        $branch2 = $this->makeBranch($business, 'S2');
        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);
        $customer = new Customer(['name' => 'C', 'is_active' => true]);
        $customer->business_id = $business->id;
        $customer->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'branch', 'branch2', 'owner', 'admin', 'customer');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch();
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

    /** @param array<int,string> $profiles */
    private function operator(object $t, array $profiles, ?Branch $branch = null): User
    {
        $branch ??= $t->branch;
        $op = $this->makeUser($t->business, RoleName::Operator, $branch);
        foreach ($profiles as $p) {
            $row = new UserOperativeProfile(['profile' => $p]);
            $row->user_id = $op->id;
            $row->business_id = $t->business->id;
            $row->save();
        }

        return $op;
    }

    private function collectibleReceivable(object $t, Branch $branch, string $status = 'pendiente'): AccountReceivable
    {
        $inv = new Invoice();
        $inv->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'customer_id' => $t->customer->id,
            'cash_session_id' => null, 'folio' => 'F-'.(++self::$seq), 'payment_type' => 'credito',
            'payment_status' => 'pendiente', 'status' => 'emitida', 'subtotal' => '100.00', 'tax_amount' => '0.00',
            'discount_amount' => '0.00', 'total' => '100.00', 'paid_amount' => '0.00',
            'issued_at' => now(), 'issued_by' => $t->owner->id,
        ])->saveQuietly();

        $ar = new AccountReceivable();
        $ar->forceFill([
            'business_id' => $t->business->id, 'customer_id' => $t->customer->id, 'invoice_id' => $inv->id,
            'total_amount' => '100.00', 'paid_amount' => '0.00', 'due_date' => now()->addDays(30)->toDateString(), 'status' => $status,
        ])->save();

        return $ar;
    }

    // ---------------- 7.2 · CxC cobrables ----------------

    public function test_cajero_consulta_cxc_cobrables_de_su_sucursal(): void
    {
        $t = $this->seedTenant();
        $this->collectibleReceivable($t, $t->branch, 'pendiente');
        $this->collectibleReceivable($t, $t->branch2, 'pendiente'); // otra sucursal.
        $this->collectibleReceivable($t, $t->branch, 'pagada');     // no cobrable (excluida).

        $cajero = $this->operator($t, ['cajero'], $t->branch);
        $data = $this->asUser($cajero)->getJson('/api/v1/accounts-receivable/collectible')->assertOk()->json('data');

        $this->assertCount(1, $data); // solo la pendiente de SU sucursal.
        $this->assertSame('pendiente', $data[0]['status']);
    }

    public function test_admin_ve_todas_las_cobrables_del_negocio(): void
    {
        $t = $this->seedTenant();
        $this->collectibleReceivable($t, $t->branch, 'pendiente');
        $this->collectibleReceivable($t, $t->branch2, 'vencida');

        $data = $this->asUser($t->admin)->getJson('/api/v1/accounts-receivable/collectible')->assertOk()->json('data');
        $this->assertCount(2, $data); // ambas sucursales.
    }

    public function test_bodeguero_no_accede_a_cxc_cobrables(): void
    {
        $t = $this->seedTenant();
        $this->asUser($this->operator($t, ['bodeguero'], $t->branch))
            ->getJson('/api/v1/accounts-receivable/collectible')->assertStatus(403);
    }

    // ---------------- 7.4 · Dashboard ROL-02 ----------------

    public function test_dashboard_admin_datos_reales(): void
    {
        $t = $this->seedTenant();
        $this->collectibleReceivable($t, $t->branch, 'vencida');

        $data = $this->asUser($t->admin)->getJson('/api/v1/dashboard/admin')->assertOk()->json('data');
        $this->assertArrayHasKey('cuentas_por_cobrar_vencidas', $data);
        $this->assertSame(1, $data['cuentas_por_cobrar_vencidas']);
        $this->assertArrayHasKey('anomalias_activas', $data);
    }

    public function test_dashboard_admin_negado_a_operador(): void
    {
        $t = $this->seedTenant();
        $this->asUser($this->operator($t, ['cajero'], $t->branch))
            ->getJson('/api/v1/dashboard/admin')->assertStatus(403);
    }

    // ---------------- 7.5 · Dashboard ROL-03 por perfiles ----------------

    public function test_dashboard_operativo_condicionado_por_perfiles(): void
    {
        $t = $this->seedTenant();
        $cajero = $this->operator($t, ['cajero'], $t->branch);

        $data = $this->asUser($cajero)->getJson('/api/v1/dashboard/operative')->assertOk()->json('data');
        $this->assertSame($t->branch->id, $data['branch_id']);
        $this->assertArrayHasKey('cajero', $data['sections']);
        $this->assertArrayNotHasKey('facturador', $data['sections']); // no tiene ese perfil.
        $this->assertArrayNotHasKey('bodeguero', $data['sections']);
    }

    public function test_dashboard_operativo_multiperfil(): void
    {
        $t = $this->seedTenant();
        $op = $this->operator($t, ['cajero', 'bodeguero'], $t->branch);

        $sections = $this->asUser($op)->getJson('/api/v1/dashboard/operative')->assertOk()->json('data.sections');
        $this->assertArrayHasKey('cajero', $sections);
        $this->assertArrayHasKey('bodeguero', $sections);
        $this->assertArrayNotHasKey('facturador', $sections);
    }
}
