<?php

declare(strict_types=1);

namespace Tests\Feature\Mod03;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-03 (microcierre) · Disponibilidad para facturar (punto 4).
 *
 * El facturador obtiene existencia/reservado/disponible de la bodega predeterminada de SU sucursal, sin
 * acceso general a bodegas ni a costos. Y al emitir, la validación transaccional de stock rechaza una
 * cantidad que supera el disponible aunque no se haya alcanzado el mínimo configurado.
 */
final class SalesAvailabilityHttpTest extends MysqlTestCase
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
        $whA1 = $this->makeWarehouse($business, $branchA, 'A1', true);
        $whB1 = $this->makeWarehouse($business, $branchB, 'B1', true);

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);
        $facturadorA = $this->makeUser($business, RoleName::Operator, $branchA, 'facturador');
        $cajeroA     = $this->makeUser($business, RoleName::Operator, $branchA, 'cajero');

        $category = new Category(['name' => 'Cat '.self::$seq]);
        $category->business_id = $business->id;
        $category->save();
        $unit = new UnitOfMeasure(['name' => 'u', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $customer = new Customer([
            'name' => 'Cliente '.$slug, 'document_type' => 'cedula',
            'document_number' => 'DOC-'.(++self::$seq), 'credit_limit' => '0.00',
        ]);
        $customer->business_id = $business->id;
        $customer->save();

        $register = new CashRegister();
        $register->forceFill(['business_id' => $business->id, 'branch_id' => $branchA->id, 'name' => 'Caja '.$slug, 'is_active' => true])->save();
        $session = new CashSession();
        $session->forceFill([
            'business_id' => $business->id, 'cash_register_id' => $register->id, 'opened_by' => $admin->id,
            'status' => 'abierta', 'opening_amount' => '0.00', 'opened_at' => now(),
        ])->saveQuietly();

        return (object) compact('business', 'branchA', 'branchB', 'whA1', 'whB1', 'owner', 'admin', 'facturadorA', 'cajeroA', 'category', 'unit', 'customer', 'session');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.(++self::$seq), 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $b, Branch $branch, string $name, bool $default): Warehouse
    {
        $w = new Warehouse;
        $w->forceFill(['business_id' => $b->id, 'branch_id' => $branch->id, 'name' => $name.(++self::$seq), 'is_default' => $default, 'is_active' => true])->save();

        return $w;
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

    private function makeProduct(object $t, string $price = '20.00'): Product
    {
        $p = new Product([
            'category_id' => $t->category->id, 'unit_id' => $t->unit->id, 'sku' => 'SKU-'.(++self::$seq),
            'name' => 'P '.self::$seq, 'type' => ProductType::Simple, 'sale_price' => $price, 'cost' => '10.00',
            'tracks_inventory' => true, 'tax_class' => TaxClass::Exempt->value, 'is_active' => true,
        ]);
        $p->business_id = $t->business->id;
        $p->save();

        return $p;
    }

    private function seedStock(object $t, Warehouse $w, Product $p, string $qty, string $reserved = '0.000', ?string $min = null): void
    {
        $s = new StockLevel;
        $s->forceFill([
            'business_id' => $t->business->id, 'product_id' => $p->id, 'warehouse_id' => $w->id,
            'quantity' => $qty, 'reserved_quantity' => $reserved, 'average_cost' => '10.0000', 'min_stock' => $min,
        ])->save();
    }

    // ===================== (4) Disponibilidad para facturar =====================

    public function test_facturador_consulta_disponibilidad_de_bodega_predeterminada_sin_costo(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000', '3.000');

        $resp = $this->asUser($t->facturadorA)->getJson('/api/v1/sales/availability')->assertOk();
        $row  = $resp->json('data.0');

        $this->assertSame($p->id, (int) $row['product_id']);
        $this->assertSame($t->whA1->id, (int) $row['warehouse_id']);
        $this->assertSame('10.000', $row['quantity']);
        $this->assertSame('3.000', $row['reserved_quantity']);
        $this->assertSame('7.000', $row['available']);
        // No se filtra costo ni datos generales de bodega.
        $this->assertArrayNotHasKey('average_cost', $row);
        $this->assertArrayNotHasKey('cost', $row);
    }

    public function test_cajero_sin_capacidad_de_venta_no_consulta_disponibilidad(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');

        // El cajero no tiene ventas.crear ni facturas.crear → 403 (no se le abre el inventario para esto).
        $this->asUser($t->cajeroA)->getJson('/api/v1/sales/availability')->assertStatus(403);
    }

    public function test_admin_debe_indicar_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');

        // ROL-02 no está acotado a una sucursal: sin branch_id → 422.
        $this->asUser($t->admin)->getJson('/api/v1/sales/availability')->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
        // Con branch_id resuelve la bodega predeterminada de esa sucursal.
        $this->asUser($t->admin)->getJson("/api/v1/sales/availability?branch_id={$t->branchA->id}")
            ->assertOk()->assertJsonPath('data.0.available', '10.000');
    }

    public function test_facturador_no_puede_consultar_otra_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->facturadorA)->getJson("/api/v1/sales/availability?branch_id={$t->branchB->id}")
            ->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_disponibilidad_aislamiento_entre_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $pa = $this->makeProduct($a);
        $this->seedStock($a, $a->whA1, $pa, '10.000');

        // El facturador de B solo ve la bodega predeterminada de SU sucursal (sin productos de A).
        $this->asUser($b->facturadorA)->getJson('/api/v1/sales/availability')->assertOk()->assertJsonCount(0, 'data');
    }

    // ===================== (4/5) Insuficiencia al emitir, aun sobre el mínimo =====================

    public function test_factura_rechaza_si_cantidad_supera_disponible_aunque_sobre_el_minimo(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t, '20.00');
        // Existencia 10, mínimo 2: disponible (10) MUY por encima del mínimo, pero se piden 15.
        $this->seedStock($t, $t->whA1, $p, '10.000', '0.000', '2.000');

        $saleId = $this->asUser($t->admin)->postJson('/api/v1/sales', [
            'branch_id' => $t->branchA->id, 'customer_id' => $t->customer->id,
        ])->assertCreated()->json('data.id');
        $this->asUser($t->admin)->postJson("/api/v1/sales/{$saleId}/items", ['product_id' => $p->id, 'quantity' => '15.000'])->assertCreated();
        $this->asUser($t->admin)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        // Exento: total = 20 * 15 = 300.00; el pago completo pasa el control de contado y se llega a la reserva.
        $this->asUser($t->admin)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'contado', 'cash_session_id' => $t->session->id,
            'payments' => [['method' => 'efectivo', 'amount' => '300.00']],
        ])->assertStatus(409)->assertJsonPath('error', 'INSUFFICIENT_STOCK');

        // Nada quedó reservado: la emisión se revirtió por completo.
        $stock = StockLevel::withoutGlobalScopes()->where('product_id', $p->id)->where('warehouse_id', $t->whA1->id)->firstOrFail();
        $this->assertSame('0.000', (string) $stock->reserved_quantity);
        $this->assertDatabaseCount('invoices', 0);
    }
}
