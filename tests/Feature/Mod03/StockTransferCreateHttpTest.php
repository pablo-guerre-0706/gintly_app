<?php

declare(strict_types=1);

namespace Tests\Feature\Mod03;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-03 · Microcierre — CREACIÓN de traspasos (POST /stock-transfers).
 *
 * Reproduce y fija el 500 por `user_id` ausente en el primer INSERT (columna NOT NULL): el servicio
 * guardaba el encabezado antes de asignar el actor. El user_id persistido debe ser el autenticado.
 * Conserva el aislamiento de sucursal ROL-03 (origen de su sucursal) y la atomicidad encabezado+líneas.
 */
final class StockTransferCreateHttpTest extends MysqlTestCase
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
        $branch1 = $this->makeBranch($business, 'S1');
        $branch2 = $this->makeBranch($business, 'S2');
        $wh1 = $this->makeWarehouse($business, $branch1, 'B1', true);
        $wh1b = $this->makeWarehouse($business, $branch1, 'B1b', false);
        $wh2 = $this->makeWarehouse($business, $branch2, 'B2', true);

        $owner = $this->makeUser($business, RoleName::Owner);
        $operator = $this->makeUser($business, RoleName::Operator, $branch1);
        $this->assignProfile($operator, $business->id, 'bodeguero');
        // RF-03 asignación Bodega–Bodeguero: el bodeguero opera las bodegas de SU sucursal (origen y destino).
        $this->assignWarehouse($operator, $wh1);
        $this->assignWarehouse($operator, $wh1b);

        $category = new Category(['name' => 'Cat '.self::$seq]);
        $category->business_id = $business->id;
        $category->save();
        $unit = new UnitOfMeasure(['name' => 'u', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();
        $product = new Product([
            'category_id' => $category->id, 'unit_id' => $unit->id, 'sku' => 'SKU-'.self::$seq,
            'name' => 'P', 'type' => ProductType::Simple, 'sale_price' => '20.00', 'cost' => '10.00',
            'tracks_inventory' => true, 'tax_class' => TaxClass::Standard->value, 'is_active' => true,
        ]);
        $product->business_id = $business->id;
        $product->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'owner', 'operator', 'branch1', 'branch2', 'wh1', 'wh1b', 'wh2', 'product');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.self::$seq, 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $b, Branch $branch, string $name, bool $isDefault): Warehouse
    {
        $w = new Warehouse;
        $w->forceFill(['business_id' => $b->id, 'branch_id' => $branch->id, 'name' => $name.self::$seq, 'is_default' => $isDefault, 'is_active' => true])->save();

        return $w;
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

    private function assignWarehouse(User $keeper, Warehouse $warehouse): void
    {
        $a = new WarehouseAssignment();
        $a->forceFill([
            'business_id' => $warehouse->business_id, 'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id, 'user_id' => $keeper->id, 'assigned_by' => $keeper->id, 'assigned_at' => now(),
        ])->save();
    }

    private function makeStock(object $t, Warehouse $w, string $qty): void
    {
        $s = new StockLevel;
        $s->forceFill([
            'business_id' => $t->business->id, 'product_id' => $t->product->id, 'warehouse_id' => $w->id,
            'quantity' => $qty, 'reserved_quantity' => '0.000', 'average_cost' => '10.0000',
        ])->save();
    }

    public function test_bodeguero_crea_traspaso_201_persiste_encabezado_lineas_y_user_id(): void
    {
        $t = $this->seedTenant('a');

        $resp = $this->asUser($t->operator)->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $t->wh1->id,   // origen en SU sucursal.
            'to_warehouse_id' => $t->wh1b->id,  // destino en la misma sucursal.
            'notes' => 'traspaso demo',
            'items' => [['product_id' => $t->product->id, 'quantity' => '2.000']],
        ])->assertCreated();

        $transferId = $resp->json('data.id');
        $this->assertNotNull($transferId);

        $transfer = StockTransfer::withoutGlobalScopes()->findOrFail($transferId);
        $this->assertSame((int) $t->operator->id, (int) $transfer->user_id); // user_id = actor autenticado.
        $this->assertSame($t->wh1->id, (int) $transfer->from_warehouse_id);
        $this->assertSame('pendiente', $transfer->status->value);

        $this->assertSame(1, StockTransferItem::withoutGlobalScopes()->where('stock_transfer_id', $transferId)->count());
    }

    public function test_traspaso_origen_de_otra_sucursal_rechazado_sin_filas(): void
    {
        $t = $this->seedTenant('a');

        $before = StockTransfer::withoutGlobalScopes()->where('business_id', $t->business->id)->count();

        // Origen = bodega de S2 (ajena al operador de S1): rechazo por aislamiento de sucursal (422).
        $this->asUser($t->operator)->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $t->wh2->id,
            'to_warehouse_id' => $t->wh1->id,
            'items' => [['product_id' => $t->product->id, 'quantity' => '1.000']],
        ])->assertStatus(422);

        $this->assertSame($before, StockTransfer::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        $this->assertSame(0, StockTransferItem::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_transicion_completar_funciona_tras_crear(): void
    {
        $t = $this->seedTenant('a');
        $this->makeStock($t, $t->wh1, '5.000'); // stock en origen para poder completar.

        $transferId = $this->asUser($t->operator)->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $t->wh1->id,
            'to_warehouse_id' => $t->wh1b->id,
            'items' => [['product_id' => $t->product->id, 'quantity' => '2.000']],
        ])->assertCreated()->json('data.id');

        $this->asUser($t->operator)->postJson("/api/v1/stock-transfers/{$transferId}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completado');

        $destino = StockLevel::withoutGlobalScopes()
            ->where('product_id', $t->product->id)->where('warehouse_id', $t->wh1b->id)->first();
        $this->assertNotNull($destino);
        $this->assertSame('2.000', (string) $destino->quantity);
    }
}
