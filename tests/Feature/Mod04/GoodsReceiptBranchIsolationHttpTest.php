<?php

declare(strict_types=1);

namespace Tests\Feature\Mod04;

use App\Enums\ProductType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RoleName;
use App\Enums\SupplierStatus;
use App\Enums\TaxClass;
use App\Models\AccountPayable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use App\Services\Purchasing\GoodsReceiptService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-04 · Microcierre — AISLAMIENTO DE SUCURSAL en la MUTACIÓN POST /goods-receipts.
 *
 * Invariante ROL-03: purchase_order.branch_id === warehouse.branch_id === user.branch_id.
 * Defensa en DOS capas: StoreGoodsReceiptRequest (422 por campo) y GoodsReceiptService
 * (AuthorizationException/403 dentro de la transacción, antes de persistir nada). Una invocación
 * interna directa al Service no puede saltarse la regla. ROL-01/ROL-02 conservan su alcance de negocio.
 */
final class GoodsReceiptBranchIsolationHttpTest extends MysqlTestCase
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

        $business = Business::withoutEvents(function () use ($slug): Business {
            $b = new Business([
                'name' => 'Negocio '.$slug, 'slug' => $slug.'-'.(++self::$seq),
                'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
            ]);
            $b->save();

            return $b;
        });
        app(RolesAndPermissionsSeeder::class)->syncBusinessRoles($business->id);

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);

        $branch1 = $this->makeBranch($business, 'S1', $admin->id);
        $branch2 = $this->makeBranch($business, 'S2', $admin->id);
        $wh1 = $this->makeWarehouse($business, $branch1, 'B1');
        $wh2 = $this->makeWarehouse($business, $branch2, 'B2');

        // ROL-03 BODEGUERO asignado a S1.
        $operator = $this->makeUser($business, RoleName::Operator, $branch1);
        $this->assignProfile($operator, $business->id, 'bodeguero');
        // RF-03 asignación Bodega–Bodeguero: habilitado a operar la bodega de S1.
        $wa = new WarehouseAssignment();
        $wa->forceFill(['business_id' => $business->id, 'branch_id' => $branch1->id, 'warehouse_id' => $wh1->id, 'user_id' => $operator->id, 'assigned_by' => $admin->id, 'assigned_at' => now()])->save();

        $category = new Category(['name' => 'Cat '.$slug]);
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

        $supplier = new Supplier(['name' => 'Prov '.(++self::$seq)]);
        $supplier->business_id = $business->id;
        $supplier->status = SupplierStatus::Aprobado;
        $supplier->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'owner', 'admin', 'operator', 'branch1', 'branch2', 'wh1', 'wh2', 'product', 'supplier');
    }

    private function makeBranch(Business $b, string $name, int $managerId): Branch
    {
        $branch = new Branch(['name' => $name.self::$seq, 'address' => 'x', 'manager_user_id' => $managerId, 'opened_at' => '2026-01-01', 'is_active' => true]);
        $branch->business_id = $b->id;
        $branch->save();

        return $branch;
    }

    private function makeWarehouse(Business $b, Branch $branch, string $name): Warehouse
    {
        $w = new Warehouse(['branch_id' => $branch->id, 'name' => $name.self::$seq, 'is_default' => true, 'is_active' => true]);
        $w->business_id = $b->id;
        $w->save();

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

    /** Crea y emite una orden en la sucursal dada (como admin, sin restricción de sucursal). Devuelve [orderId, itemId]. */
    private function issuedOrder(object $t, Branch $branch): array
    {
        $create = $this->asUser($t->admin)->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $t->supplier->id, 'branch_id' => $branch->id, 'ordered_at' => '2026-02-01',
            'items' => [['product_id' => $t->product->id, 'ordered_quantity' => '5.000', 'agreed_unit_cost' => '10.0000']],
        ])->assertCreated();
        $orderId = $create->json('data.id');
        $itemId = $create->json('data.items.0.id');
        $this->asUser($t->admin)->postJson("/api/v1/purchase-orders/{$orderId}/issue")->assertOk();

        return [(int) $orderId, (int) $itemId];
    }

    /** @return array<string,mixed> */
    private function receiptPayload(int $orderId, int $warehouseId, int $itemId): array
    {
        return [
            'purchase_order_id' => $orderId, 'warehouse_id' => $warehouseId, 'supplier_invoice_total' => '50.00',
            'tolerance' => '0', 'lines' => [['purchase_order_item_id' => $itemId, 'received_quantity' => '5.000', 'invoiced_unit_cost' => '10.0000']],
        ];
    }

    private function assertNoReceiptRows(int $orderId, int $warehouseId): void
    {
        $this->assertSame(0, GoodsReceipt::withoutGlobalScopes()->where('purchase_order_id', $orderId)->count(), 'goods_receipts no debe tener filas');
        $this->assertSame(0, AccountPayable::withoutGlobalScopes()->where('purchase_order_id', $orderId)->count(), 'accounts_payable no debe tener filas');
        $this->assertSame(0, InventoryMovement::withoutGlobalScopes()->where('purchase_order_id', $orderId)->where('warehouse_id', $warehouseId)->count(), 'inventory_movements no debe tener filas');
    }

    // ---------------- 1) Orden A2 + bodega A1 por ROL-03 de A1 → rechazo ----------------

    public function test_orden_a2_bodega_a1_rechazada(): void
    {
        $t = $this->seedTenant('a');
        [$orderA2, $itemA2] = $this->issuedOrder($t, $t->branch2); // orden en S2.

        $this->asUser($t->operator)
            ->postJson('/api/v1/goods-receipts', $this->receiptPayload($orderA2, $t->wh1->id, $itemA2))
            ->assertStatus(422)->assertJsonValidationErrors(['purchase_order_id']);

        $this->assertNoReceiptRows($orderA2, $t->wh1->id);
    }

    // ---------------- 2) Orden A1 + bodega A2 por ROL-03 de A1 → rechazo ----------------

    public function test_orden_a1_bodega_a2_rechazada(): void
    {
        $t = $this->seedTenant('a');
        [$orderA1, $itemA1] = $this->issuedOrder($t, $t->branch1);

        $this->asUser($t->operator)
            ->postJson('/api/v1/goods-receipts', $this->receiptPayload($orderA1, $t->wh2->id, $itemA1))
            ->assertStatus(422)->assertJsonValidationErrors(['warehouse_id']);

        $this->assertNoReceiptRows($orderA1, $t->wh2->id);
    }

    // ---------------- 3) Orden A1 + bodega A1 → válida ----------------

    public function test_orden_a1_bodega_a1_valida(): void
    {
        $t = $this->seedTenant('a');
        [$orderA1, $itemA1] = $this->issuedOrder($t, $t->branch1);

        $this->asUser($t->operator)
            ->postJson('/api/v1/goods-receipts', $this->receiptPayload($orderA1, $t->wh1->id, $itemA1))
            ->assertCreated()->assertJsonPath('data.match_status', 'ok');

        $this->assertSame(1, GoodsReceipt::withoutGlobalScopes()->where('purchase_order_id', $orderA1)->count());
    }

    // ---------------- 4) ROL-03 sin sucursal → rechazo ----------------

    public function test_rol03_sin_sucursal_rechazado(): void
    {
        $t = $this->seedTenant('a');
        [$orderA1, $itemA1] = $this->issuedOrder($t, $t->branch1);
        $branchless = $this->makeUser($t->business, RoleName::Operator, null); // sin sucursal.
        $this->assignProfile($branchless, $t->business->id, 'bodeguero');

        $this->asUser($branchless)
            ->postJson('/api/v1/goods-receipts', $this->receiptPayload($orderA1, $t->wh1->id, $itemA1))
            ->assertStatus(422)->assertJsonValidationErrors(['purchase_order_id', 'warehouse_id']);

        $this->assertNoReceiptRows($orderA1, $t->wh1->id);
    }

    // ---------------- 5) Recurso de otro negocio → rechazo multitenant ----------------

    public function test_cross_tenant_rechazado(): void
    {
        $t = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        [$orderB, $itemB] = $this->issuedOrder($b, $b->branch1); // orden del negocio B.

        // El bodeguero de A intenta recibir la orden de B con su bodega de A → 422 (exists multitenant).
        $this->asUser($t->operator)
            ->postJson('/api/v1/goods-receipts', $this->receiptPayload($orderB, $t->wh1->id, $itemB))
            ->assertStatus(422)->assertJsonValidationErrors(['purchase_order_id']);

        $this->assertNoReceiptRows($orderB, $t->wh1->id);
    }

    // ---------------- 6) ROL-01/ROL-02 conservan su comportamiento ----------------

    public function test_admin_recibe_cualquier_sucursal(): void
    {
        $t = $this->seedTenant('a');
        [$orderA2, $itemA2] = $this->issuedOrder($t, $t->branch2);

        // Admin NO está acotado a sucursal: recibe la orden de S2 en la bodega de S2.
        $this->asUser($t->admin)
            ->postJson('/api/v1/goods-receipts', $this->receiptPayload($orderA2, $t->wh2->id, $itemA2))
            ->assertCreated()->assertJsonPath('data.match_status', 'ok');
    }

    // ---------------- 7) Invocación directa al Service evade el FormRequest → rechazo ----------------

    public function test_service_directo_cross_branch_rechazado_sin_filas(): void
    {
        $t = $this->seedTenant('a');
        [$orderA2, $itemA2] = $this->issuedOrder($t, $t->branch2);

        // Contexto de equipo para que isOperator()/getRoleNames resuelvan el rol.
        app(PermissionRegistrar::class)->setPermissionsTeamId($t->business->id);
        $operator = User::query()->whereKey($t->operator->id)->firstOrFail();

        $threw = false;
        try {
            app(GoodsReceiptService::class)->recibir(
                $operator, $orderA2, $t->wh1->id,
                [['purchase_order_item_id' => $itemA2, 'received_quantity' => '5.000', 'invoiced_unit_cost' => '10.0000']],
                null, '50.00', '0',
            );
        } catch (AuthorizationException $e) {
            $threw = true;
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        $this->assertTrue($threw, 'El Service debe rechazar la recepción cross-branch de un ROL-03.');
        $this->assertNoReceiptRows($orderA2, $t->wh1->id);
    }

    // ---------------- 8) El hook 3-way sigue intacto para el caso válido (control) ----------------

    public function test_orden_a1_bodega_a1_sin_discrepancia_no_crea_anomalia(): void
    {
        $t = $this->seedTenant('a');
        [$orderA1, $itemA1] = $this->issuedOrder($t, $t->branch1);

        $this->asUser($t->operator)
            ->postJson('/api/v1/goods-receipts', $this->receiptPayload($orderA1, $t->wh1->id, $itemA1))
            ->assertCreated();

        // Caso OK: la orden queda recibida y la CxP pendiente (comportamiento vigente preservado).
        $this->assertDatabaseHas('purchase_orders', ['id' => $orderA1, 'status' => PurchaseOrderStatus::Recibida->value]);
        $this->assertDatabaseHas('accounts_payable', ['purchase_order_id' => $orderA1, 'status' => 'pendiente']);
    }
}
