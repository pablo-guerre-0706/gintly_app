<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashRegisterAssignment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
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
 * MOD-01 · Fase 5 — Enforcement operativo: rol humano + perfil ROL-03 + sucursal + negocio.
 *
 * Prueba que ningún ROL-03 obtiene una operación solo porque su rol Spatie contiene la unión de
 * permisos: debe superar operativeCan() (perfil) y el alcance de sucursal/negocio. Cubre positivos
 * y negativos por perfil, combinaciones, cross-branch, cross-tenant, ausencia de bypass por permisos
 * Spatie, opción B (sin perfiles → bloqueado) y que ROL-01/02 conservan sus facultades.
 */
final class OperativeEnforcementHttpTest extends MysqlTestCase
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
        $branch  = $this->makeBranch($business, 'S1');
        $branch2 = $this->makeBranch($business, 'S2');
        $warehouse = $this->makeWarehouse($business, $branch, 'B1');

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);

        $register = new CashRegister();
        $register->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Caja '.self::$seq, 'is_active' => true])->save();

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

        return (object) compact('business', 'branch', 'branch2', 'warehouse', 'owner', 'admin', 'register', 'product', 'customer');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch();
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.self::$seq, 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $b, Branch $branch, string $name): Warehouse
    {
        $w = new Warehouse();
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

    private function openCash(User $op, CashRegister $register): \Illuminate\Testing\TestResponse
    {
        return $this->asUser($op)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $register->id, 'opening_amount' => '0.00',
        ]);
    }

    /** RF-06 · Asignación Caja–Cajero activa (precondición para abrir la caja un ROL-03). */
    private function assignCajero(User $cashier, CashRegister $register): void
    {
        $assignment = new CashRegisterAssignment();
        $assignment->forceFill([
            'business_id'      => $register->business_id,
            'branch_id'        => $register->branch_id,
            'cash_register_id' => $register->id,
            'user_id'          => $cashier->id,
            'assigned_by'      => $cashier->id,
            'assigned_at'      => now(),
        ])->save();
    }

    private function openSale(User $op, object $t, ?int $branchId = null): \Illuminate\Testing\TestResponse
    {
        return $this->asUser($op)->postJson('/api/v1/sales', [
            'branch_id' => $branchId ?? $t->branch->id, 'customer_id' => $t->customer->id,
        ]);
    }

    private function countStock(User $op, object $t, ?int $warehouseId = null): \Illuminate\Testing\TestResponse
    {
        return $this->asUser($op)->postJson('/api/v1/physical-counts', [
            'warehouse_id' => $warehouseId ?? $t->warehouse->id, 'product_id' => $t->product->id, 'counted_quantity' => '1.000',
        ]);
    }

    /** RF-03 · Asignación Bodega–Bodeguero activa (precondición para operar la bodega un ROL-03). */
    private function assignWarehouse(User $keeper, Warehouse $warehouse): void
    {
        $a = new WarehouseAssignment();
        $a->forceFill([
            'business_id' => $warehouse->business_id, 'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id, 'user_id' => $keeper->id, 'assigned_by' => $keeper->id, 'assigned_at' => now(),
        ])->save();
    }

    // ---------------- Perfil CAJERO (caja) ----------------

    public function test_cajero_abre_caja_pero_bodeguero_no(): void
    {
        $t = $this->seedTenant('a');

        $cajero = $this->operator($t, ['cajero']);
        $this->assignCajero($cajero, $t->register); // RF-06: abrir exige caja asignada.
        $this->openCash($cajero, $t->register)->assertStatus(201);
        $this->openCash($this->operator($t, ['bodeguero']), $t->register)->assertStatus(403);
    }

    public function test_sin_bypass_por_permiso_spatie_en_caja(): void
    {
        $t = $this->seedTenant('a');
        $bodeguero = $this->operator($t, ['bodeguero']);

        // El ROL Spatie contiene 'caja.abrir' (unión de perfiles), pero el PERFIL no lo habilita.
        app(PermissionRegistrar::class)->setPermissionsTeamId($t->business->id);
        $this->assertTrue($bodeguero->fresh()->hasPermissionTo('caja.abrir'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->openCash($bodeguero, $t->register)->assertStatus(403); // el perfil gobierna, no el permiso del rol.
    }

    // ---------------- Perfil FACTURADOR (ventas) ----------------

    public function test_facturador_crea_venta_pero_cajero_no(): void
    {
        $t = $this->seedTenant('a');

        $this->openSale($this->operator($t, ['facturador']), $t)->assertStatus(201);
        $this->openSale($this->operator($t, ['cajero']), $t)->assertStatus(403);
    }

    // ---------------- Perfil BODEGUERO (conteo) ----------------

    public function test_bodeguero_cuenta_pero_facturador_no(): void
    {
        $t = $this->seedTenant('a');

        $bodeguero = $this->operator($t, ['bodeguero']);
        $this->assignWarehouse($bodeguero, $t->warehouse); // RF-03: operar exige bodega asignada.
        $this->countStock($bodeguero, $t)->assertStatus(201);
        $this->countStock($this->operator($t, ['facturador']), $t)->assertStatus(403);
    }

    // ---------------- Opción B: sin perfiles, bloqueado ----------------

    public function test_operador_sin_perfiles_bloqueado_en_todo(): void
    {
        $t = $this->seedTenant('a');
        $bare = $this->operator($t, []); // ROL-03 sin perfiles.

        $this->openCash($bare, $t->register)->assertStatus(403);
        $this->openSale($bare, $t)->assertStatus(403);
        $this->countStock($bare, $t)->assertStatus(403);
    }

    // ---------------- Combinación de perfiles ----------------

    public function test_combinacion_cajero_facturador(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['cajero', 'facturador']);
        $this->assignCajero($op, $t->register); // RF-06: abrir exige caja asignada.

        $this->openCash($op, $t->register)->assertStatus(201);
        $this->openSale($op, $t)->assertStatus(201);
        $this->countStock($op, $t)->assertStatus(403); // no bodeguero.
    }

    // ---------------- Cross-branch ----------------

    public function test_cross_branch_venta_rechazada(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['facturador'], $t->branch); // opera en S1.

        // Intenta abrir venta en S2 → 422 (solo su sucursal).
        $this->openSale($op, $t, $t->branch2->id)->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_cross_branch_conteo_rechazado(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['bodeguero'], $t->branch);
        $w2 = $this->makeWarehouse($t->business, $t->branch2, 'B2'); // bodega de S2.

        $this->countStock($op, $t, $w2->id)->assertStatus(422)->assertJsonValidationErrors(['warehouse_id']);
    }

    // ---------------- Cross-tenant ----------------

    public function test_cross_tenant_caja_rechazada(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $opA = $this->operator($a, ['cajero'], $a->branch);

        // El operador de A intenta abrir la caja de B → 422 (la caja no es de su negocio).
        $this->openCash($opA, $b->register)->assertStatus(422);
    }

    // ---------------- Bodegas y stock: ROL-03 solo su sucursal ----------------

    public function test_rol03_solo_lista_bodegas_de_su_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $this->makeWarehouse($t->business, $t->branch2, 'B2'); // bodega de otra sucursal.
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        $data = $this->asUser($op)->getJson('/api/v1/warehouses')->assertOk()->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $w) {
            $this->assertSame($t->branch->id, $w['branch_id']); // solo S1.
        }
    }

    public function test_rol03_solo_lista_stock_de_bodegas_asignadas(): void
    {
        $t = $this->seedTenant('a');
        $w2 = $this->makeWarehouse($t->business, $t->branch2, 'B2');
        $this->makeStock($t, $t->warehouse);  // S1 (se asignará)
        $this->makeStock($t, $w2);            // S2 (no asignada)
        $op = $this->operator($t, ['bodeguero'], $t->branch);
        // Microcierre MOD-03: la visibilidad de inventario de ROL-03 exige asignación ACTIVA de la bodega.
        $this->assignWarehouse($op, $t->warehouse);

        $data = $this->asUser($op)->getJson('/api/v1/stock')->assertOk()->json('data');
        $this->assertCount(1, $data); // solo la existencia de la bodega ASIGNADA (S1).
        $this->assertSame($t->warehouse->id, (int) $data[0]['warehouse_id']);
    }

    // ---------------- Abono CxC cross-branch ----------------

    public function test_abono_cxc_de_otra_sucursal_bloqueado(): void
    {
        $t = $this->seedTenant('a');
        $arS2 = $this->makeReceivable($t, $t->branch2); // CxC de una factura de S2.
        $cajero = $this->operator($t, ['cajero'], $t->branch); // cajero de S1.

        $this->asUser($cajero)->postJson("/api/v1/accounts-receivable/{$arS2->id}/payments", [
            'amount' => '10.00', 'payment_method' => 'transferencia',
        ])->assertStatus(403); // la factura pertenece a otra sucursal.
    }

    private function makeStock(object $t, Warehouse $warehouse): void
    {
        $s = new \App\Models\StockLevel();
        $s->forceFill([
            'business_id' => $t->business->id, 'product_id' => $t->product->id, 'warehouse_id' => $warehouse->id,
            'quantity' => '5.000', 'reserved_quantity' => '0.000', 'average_cost' => '4.0000',
        ])->save();
    }

    private function makeReceivable(object $t, Branch $branch): \App\Models\AccountReceivable
    {
        $inv = new \App\Models\Invoice();
        $inv->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'customer_id' => $t->customer->id,
            'cash_session_id' => null, 'folio' => 'F-'.(++self::$seq), 'payment_type' => 'credito',
            'payment_status' => 'pendiente', 'status' => 'emitida', 'subtotal' => '100.00', 'tax_amount' => '0.00',
            'discount_amount' => '0.00', 'total' => '100.00', 'paid_amount' => '0.00', 'issued_at' => now(), 'issued_by' => $t->owner->id,
        ])->saveQuietly();

        $ar = new \App\Models\AccountReceivable();
        $ar->forceFill([
            'business_id' => $t->business->id, 'customer_id' => $t->customer->id, 'invoice_id' => $inv->id,
            'total_amount' => '100.00', 'paid_amount' => '0.00', 'due_date' => now()->addDays(30)->toDateString(), 'status' => 'pendiente',
        ])->save();

        return $ar;
    }

    // ---------------- ROL-01/02 conservan facultades ----------------

    public function test_roles_superiores_conservan_facultades_sin_perfil(): void
    {
        $t = $this->seedTenant('a');

        // Owner y Admin operan sin necesidad de perfiles ni restricción de sucursal.
        $this->openCash($t->owner, $t->register)->assertStatus(201);
        $this->openSale($t->admin, $t)->assertStatus(201);
        $this->countStock($t->admin, $t)->assertStatus(201);
    }
}
