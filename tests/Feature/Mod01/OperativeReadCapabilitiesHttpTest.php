<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
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
 * MOD-01 · Microcierre — CAPACIDAD DE LECTURA de ROL-03 en Policies (viewAny/view).
 *
 * Invariante: para ROL-03 la lectura exige DOS controles independientes — la capacidad efectiva del
 * perfil (operativeCan) Y el alcance de sucursal. El scope NO reemplaza la capacidad. Un ROL-03 de la
 * sucursal correcta pero SIN la capacidad del perfil NO puede listar ni consultar el recurso (403).
 * ROL-01/ROL-02 conservan su acceso. Ningún permiso Spatie agregado del rol ROL-03 permite saltarse el
 * perfil (la fuente fina es operativeCan).
 */
final class OperativeReadCapabilitiesHttpTest extends MysqlTestCase
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
        $branch2 = $this->makeBranch($business, 'S2');
        $wh1 = $this->makeWarehouse($business, $branch, 'B1');
        $wh2 = $this->makeWarehouse($business, $branch2, 'B2');

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);

        $category = new Category(['name' => 'Cat '.self::$seq]);
        $category->business_id = $business->id;
        $category->save();
        $unit = new UnitOfMeasure(['name' => 'u', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();
        $product = new Product([
            'category_id' => $category->id, 'unit_id' => $unit->id, 'sku' => 'SKU-'.self::$seq,
            'name' => 'P', 'type' => 'simple', 'sale_price' => '10.00', 'cost' => '4.0000',
            'tracks_inventory' => true, 'tax_class' => 'standard', 'is_active' => true,
        ]);
        $product->business_id = $business->id;
        $product->save();
        $customer = new Customer(['name' => 'C', 'is_active' => true]);
        $customer->business_id = $business->id;
        $customer->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'branch', 'branch2', 'wh1', 'wh2', 'owner', 'admin', 'product', 'customer');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.self::$seq, 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $b, Branch $branch, string $name): Warehouse
    {
        $w = new Warehouse;
        $w->forceFill(['business_id' => $b->id, 'branch_id' => $branch->id, 'name' => $name.self::$seq, 'is_default' => true, 'is_active' => true])->save();

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

    private function makeSale(object $t, Branch $branch): Sale
    {
        $s = new Sale;
        $s->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'customer_id' => $t->customer->id,
            'user_id' => $t->owner->id, 'code' => 'V-'.(++self::$seq), 'status' => 'confirmada',
            'subtotal' => '10.00', 'opened_at' => now(), 'confirmed_at' => now(),
        ])->save();

        return $s;
    }

    private function makeInvoice(object $t, Branch $branch): Invoice
    {
        $inv = new Invoice;
        $inv->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'customer_id' => $t->customer->id,
            'cash_session_id' => null, 'folio' => 'F-'.(++self::$seq), 'payment_type' => 'contado',
            'payment_status' => 'pagada', 'status' => 'emitida', 'subtotal' => '100.00', 'tax_amount' => '0.00',
            'discount_amount' => '0.00', 'total' => '100.00', 'paid_amount' => '100.00', 'issued_at' => now(), 'issued_by' => $t->owner->id,
        ])->saveQuietly();

        return $inv;
    }

    /**
     * Matriz índice: [endpoint, capacidad]. Cada perfil que NO tiene la capacidad → 403; con ella → 200.
     *
     * @return array<string,string>
     */
    private function indexMatrix(): array
    {
        return [
            '/api/v1/sales' => 'ventas.ver',
            '/api/v1/invoices' => 'facturas.ver',
            '/api/v1/dispatches' => 'entregas.ver',
            '/api/v1/stock-transfers' => 'inventario.traspaso',
            '/api/v1/goods-receipts' => 'compras.ver',
            '/api/v1/warehouses' => 'bodegas.ver',
            '/api/v1/products' => 'catalogo.ver',
            '/api/v1/stock' => 'inventario.ver',
            '/api/v1/physical-counts' => 'inventario.conteo',
            '/api/v1/customers' => 'clientes.ver',
        ];
    }

    // ---------------- Índices por perfil (capacidad exacta) ----------------

    public function test_cajero_indices_segun_capacidad(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['cajero'], $t->branch);
        // cajero: facturas.ver, clientes.ver, cuentas_por_cobrar.*, caja.* (sin ventas/entregas/inventario/catalogo/bodegas).
        $this->asUser($op)->getJson('/api/v1/invoices')->assertOk();
        $this->asUser($op)->getJson('/api/v1/customers')->assertOk();
        $this->asUser($op)->getJson('/api/v1/sales')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/dispatches')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/warehouses')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/stock-transfers')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/goods-receipts')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/products')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/stock')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/physical-counts')->assertStatus(403);
    }

    public function test_facturador_indices_segun_capacidad(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['facturador'], $t->branch);
        // facturador: ventas.ver, facturas.ver, catalogo.ver, clientes.ver.
        $this->asUser($op)->getJson('/api/v1/sales')->assertOk();
        $this->asUser($op)->getJson('/api/v1/invoices')->assertOk();
        $this->asUser($op)->getJson('/api/v1/products')->assertOk();
        $this->asUser($op)->getJson('/api/v1/customers')->assertOk();
        $this->asUser($op)->getJson('/api/v1/warehouses')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/stock-transfers')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/goods-receipts')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/dispatches')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/stock')->assertStatus(403);
    }

    public function test_bodeguero_indices_segun_capacidad(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['bodeguero'], $t->branch);
        // bodeguero: bodegas.ver, inventario.ver, inventario.conteo, inventario.traspaso, compras.ver, proveedores.ver, CxP, devoluciones, NC.
        $this->asUser($op)->getJson('/api/v1/warehouses')->assertOk();
        $this->asUser($op)->getJson('/api/v1/stock')->assertOk();
        $this->asUser($op)->getJson('/api/v1/stock-transfers')->assertOk();
        $this->asUser($op)->getJson('/api/v1/goods-receipts')->assertOk();
        $this->asUser($op)->getJson('/api/v1/physical-counts')->assertOk();
        // No vende ni despacha ni factura, y NO ve catálogo ni clientes (no son sus capacidades).
        $this->asUser($op)->getJson('/api/v1/sales')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/dispatches')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/invoices')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/products')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/customers')->assertStatus(403);
    }

    public function test_despachador_indices_segun_capacidad(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['despachador'], $t->branch);
        // despachador: facturas.ver, entregas.ver.
        $this->asUser($op)->getJson('/api/v1/invoices')->assertOk();
        $this->asUser($op)->getJson('/api/v1/dispatches')->assertOk();
        $this->asUser($op)->getJson('/api/v1/sales')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/warehouses')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/stock-transfers')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/products')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/customers')->assertStatus(403);
    }

    public function test_operador_sin_perfiles_bloqueado_en_todas_las_lecturas(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, [], $t->branch);
        foreach (array_keys($this->indexMatrix()) as $url) {
            $this->asUser($op)->getJson($url)->assertStatus(403);
        }
    }

    public function test_multiperfil_union_exacta(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['cajero', 'facturador'], $t->branch);
        // Unión: ventas.ver + facturas.ver + catalogo.ver + clientes.ver + caja/CxC.
        $this->asUser($op)->getJson('/api/v1/sales')->assertOk();
        $this->asUser($op)->getJson('/api/v1/invoices')->assertOk();
        $this->asUser($op)->getJson('/api/v1/products')->assertOk();
        $this->asUser($op)->getJson('/api/v1/customers')->assertOk();
        // Nada de bodega/traspaso/entrega (ningún perfil los concede).
        $this->asUser($op)->getJson('/api/v1/warehouses')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/stock-transfers')->assertStatus(403);
        $this->asUser($op)->getJson('/api/v1/dispatches')->assertStatus(403);
    }

    public function test_sin_bypass_por_permiso_spatie_en_lectura(): void
    {
        $t = $this->seedTenant('a');
        // Operador con perfil CAJERO (no tiene ventas.ver por perfil), pero el ROL Spatie ROL-03 SÍ
        // contiene 'ventas.ver' (unión de todos los perfiles). El perfil debe gobernar, no el permiso del rol.
        $op = $this->operator($t, ['cajero'], $t->branch);
        app(PermissionRegistrar::class)->setPermissionsTeamId($t->business->id);
        $this->assertTrue($op->fresh()->hasPermissionTo('ventas.ver'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->asUser($op)->getJson('/api/v1/sales')->assertStatus(403);
    }

    // ---------------- Detalle (view): capacidad + sucursal ----------------

    public function test_detalle_venta_requiere_ventas_ver_y_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $saleA1 = $this->makeSale($t, $t->branch);
        $saleA2 = $this->makeSale($t, $t->branch2);

        $facturador = $this->operator($t, ['facturador'], $t->branch);
        $this->asUser($facturador)->getJson("/api/v1/sales/{$saleA1->id}")->assertOk();       // capacidad + su sucursal.
        $this->asUser($facturador)->getJson("/api/v1/sales/{$saleA2->id}")->assertStatus(403); // otra sucursal.

        $cajero = $this->operator($t, ['cajero'], $t->branch);
        $this->asUser($cajero)->getJson("/api/v1/sales/{$saleA1->id}")->assertStatus(403);     // sin ventas.ver.
    }

    public function test_detalle_factura_requiere_facturas_ver(): void
    {
        $t = $this->seedTenant('a');
        $invA1 = $this->makeInvoice($t, $t->branch);

        $cajero = $this->operator($t, ['cajero'], $t->branch);
        $this->asUser($cajero)->getJson("/api/v1/invoices/{$invA1->id}")->assertOk();          // cajero tiene facturas.ver.

        $bodeguero = $this->operator($t, ['bodeguero'], $t->branch);
        $this->asUser($bodeguero)->getJson("/api/v1/invoices/{$invA1->id}")->assertStatus(403); // sin facturas.ver.
    }

    public function test_detalle_bodega_requiere_bodegas_ver_y_sucursal(): void
    {
        $t = $this->seedTenant('a');

        $bodeguero = $this->operator($t, ['bodeguero'], $t->branch);
        $this->asUser($bodeguero)->getJson("/api/v1/warehouses/{$t->wh1->id}")->assertOk();        // su sucursal.
        $this->asUser($bodeguero)->getJson("/api/v1/warehouses/{$t->wh2->id}")->assertStatus(403);  // otra sucursal.

        $cajero = $this->operator($t, ['cajero'], $t->branch);
        $this->asUser($cajero)->getJson("/api/v1/warehouses/{$t->wh1->id}")->assertStatus(403);     // sin bodegas.ver.
    }

    // ---------------- ROL-01/ROL-02 sin regresión ----------------

    public function test_roles_superiores_leen_todo_sin_perfil(): void
    {
        $t = $this->seedTenant('a');
        foreach (array_keys($this->indexMatrix()) as $url) {
            $this->asUser($t->admin)->getJson($url)->assertOk();
            $this->asUser($t->owner)->getJson($url)->assertOk();
        }
    }

    // ---------------- Query manipulada no amplía alcance ----------------

    public function test_filtro_branch_id_no_amplia_lectura(): void
    {
        $t = $this->seedTenant('a');
        $this->makeSale($t, $t->branch);
        $this->makeSale($t, $t->branch2);
        $op = $this->operator($t, ['facturador'], $t->branch);

        $data = $this->asUser($op)->getJson('/api/v1/sales?branch_id='.$t->branch2->id)->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($t->branch->id, $data[0]['branch_id']);
    }
}
