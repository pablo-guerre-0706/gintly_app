<?php

declare(strict_types=1);

namespace Tests\Feature\Mod04;

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserOperativeProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-04 · Microcierre — CREACIÓN de proveedores (POST /suppliers).
 *
 * Reproduce y fija el 500 al serializar SupplierResource tras crear: status (enum) e is_active no son
 * fillable y el DEFAULT del motor no hidrataba el modelo recién creado (status=null → status->value 500).
 * El estado inicial de dominio ('pendiente', activo) se fija en el modelo ANTES de guardar.
 */
final class SupplierCreateHttpTest extends MysqlTestCase
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
        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator);
        // Bodeguero (tiene proveedores.ver pero NO crear): confirma que la creación sigue vedada a ROL-03.
        $row = new UserOperativeProfile(['profile' => 'bodeguero']);
        $row->user_id = $operator->id;
        $row->business_id = $business->id;
        $row->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'owner', 'admin', 'operator');
    }

    private function makeUser(Business $b, RoleName $role): User
    {
        $u = new User(['name' => $role->value.self::$seq, 'email' => 'u'.(++self::$seq).'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $u;
    }

    public function test_crear_proveedor_201_con_resource_valido_y_una_sola_fila(): void
    {
        $t = $this->seedTenant('a');

        $resp = $this->asUser($t->admin)->postJson('/api/v1/suppliers', [
            'name' => 'Proveedor Uno',
            'tax_id' => 'J0310000000001',
            'email' => 'prov@demo.local',
            'phone' => '88887777',
        ]);

        $resp->assertCreated()
            ->assertJsonPath('data.name', 'Proveedor Uno')
            ->assertJsonPath('data.status', 'pendiente')
            ->assertJsonPath('data.status_label', 'Pendiente de aprobación')
            ->assertJsonPath('data.is_active', true);

        // Exactamente una fila, sin necesidad de reintentar tras un 500.
        $this->assertSame(1, Supplier::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        $supplier = Supplier::withoutGlobalScopes()->where('business_id', $t->business->id)->firstOrFail();
        $this->assertSame('pendiente', $supplier->status->value); // casteado correctamente.
    }

    public function test_rol03_no_puede_crear_proveedores(): void
    {
        $t = $this->seedTenant('a');

        $this->asUser($t->operator)->postJson('/api/v1/suppliers', ['name' => 'X'])
            ->assertStatus(403);

        $this->assertSame(0, Supplier::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }
}
