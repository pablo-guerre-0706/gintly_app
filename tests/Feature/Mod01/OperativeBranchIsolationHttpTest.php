<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Enums\RoleName;
use App\Models\AccountPayable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PhysicalCount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use App\Services\Inventory\StockTransferService;
use App\Services\Returns\ReturnService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-01 · Microcierre ROL-03 — AISLAMIENTO DE SUCURSAL EN LOS LISTADOS y usabilidad de capacidades.
 *
 * El detalle y las mutaciones ya se protegen por Policy (operatorInBranch); aquí se prueba la otra
 * mitad del aislamiento: el ÍNDICE no puede devolver filas de otra sucursal ni de otro negocio, y un
 * filtro branch_id manipulado no amplía el alcance. Además verifica que las capacidades del BODEGUERO
 * que antes quedaban inútiles (proveedores/compras/CxP/devoluciones/notas de crédito en consulta) ahora
 * funcionan, acotadas a su sucursal, sin conceder potestades de ROL-01/ROL-02.
 */
final class OperativeBranchIsolationHttpTest extends MysqlTestCase
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

        return (object) compact('business', 'branch', 'branch2', 'wh1', 'wh2', 'owner', 'admin', 'product', 'customer');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.self::$seq, 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $b, Branch $branch, string $name, bool $isDefault = true): Warehouse
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

    /** MOD-03 (microcierre) · Asignación Bodega–Bodeguero activa (precondición de visibilidad de inventario). */
    private function assignWarehouse(object $t, User $keeper, Warehouse $warehouse): void
    {
        $a = new WarehouseAssignment;
        $a->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id, 'user_id' => $keeper->id, 'assigned_by' => $t->admin->id, 'assigned_at' => now(),
        ])->save();
    }

    // ---------------- Builders de recursos (forceFill: sin factories en el proyecto) ----------------

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

    private function makePhysicalCount(object $t, Warehouse $warehouse): PhysicalCount
    {
        $c = new PhysicalCount;
        $c->forceFill([
            'business_id' => $t->business->id, 'product_id' => $t->product->id, 'warehouse_id' => $warehouse->id,
            'user_id' => $t->owner->id, 'system_quantity' => '5.000', 'counted_quantity' => '5.000',
            'status' => 'abierto', 'counted_at' => now(),
        ])->save();

        return $c;
    }

    private function makeSupplier(object $t): Supplier
    {
        $s = new Supplier;
        $s->forceFill([
            'business_id' => $t->business->id, 'name' => 'Prov '.(++self::$seq), 'status' => 'aprobado', 'is_active' => true,
        ])->save();

        return $s;
    }

    private function makePurchaseOrder(object $t, Branch $branch, ?Supplier $supplier = null): PurchaseOrder
    {
        $supplier ??= $this->makeSupplier($t);
        $o = new PurchaseOrder;
        $o->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
            'user_id' => $t->owner->id, 'code' => 'OC-'.(++self::$seq), 'status' => 'emitida',
            'expected_total' => '50.00', 'ordered_at' => now()->toDateString(),
        ])->save();

        return $o;
    }

    private function makeAccountPayable(object $t, PurchaseOrder $order): AccountPayable
    {
        $ap = new AccountPayable;
        $ap->forceFill([
            'business_id' => $t->business->id, 'supplier_id' => $order->supplier_id, 'purchase_order_id' => $order->id,
            'total_amount' => '50.00', 'paid_amount' => '0.00', 'status' => 'pendiente', 'due_date' => now()->addDays(30)->toDateString(),
        ])->save();

        return $ap;
    }

    private function makeStockTransfer(object $t, Warehouse $from, Warehouse $to): StockTransfer
    {
        $tr = new StockTransfer;
        $tr->forceFill([
            'business_id' => $t->business->id, 'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id,
            'user_id' => $t->owner->id, 'code' => 'TR-'.(++self::$seq), 'status' => 'pendiente', 'transferred_at' => now(),
        ])->save();

        return $tr;
    }

    private function makeSalesReturn(object $t, Branch $branch, Invoice $invoice): SalesReturn
    {
        $r = new SalesReturn;
        $r->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'invoice_id' => $invoice->id,
            'customer_id' => $t->customer->id, 'user_id' => $t->owner->id, 'code' => 'DV-'.(++self::$seq),
            'status' => 'registrada', 'total_returned' => '10.00', 'returned_at' => now(),
        ])->save();

        return $r;
    }

    private function makeCreditNote(object $t, Invoice $invoice, SalesReturn $return): CreditNote
    {
        $cn = new CreditNote;
        $cn->forceFill([
            'business_id' => $t->business->id, 'invoice_id' => $invoice->id, 'sales_return_id' => $return->id,
            'customer_id' => $t->customer->id, 'issued_by' => $t->owner->id, 'folio' => 'NC-'.(++self::$seq),
            'resolution_type' => 'nota_credito_saldo', 'total_amount' => '10.00', 'tax_amount' => '0.00',
            'status' => 'emitida', 'issued_at' => now(),
        ])->save();

        return $cn;
    }

    // ============================================================
    //  A) Aislamiento de índice — recursos de sucursal DIRECTA
    // ============================================================

    public function test_ventas_indice_solo_sucursal_del_operador(): void
    {
        $t = $this->seedTenant('a');
        $b = $this->seedTenant('b'); // otro negocio.
        $this->makeSale($t, $t->branch);   // A1
        $this->makeSale($t, $t->branch2);  // A2
        $this->makeSale($b, $b->branch);   // negocio B
        $op = $this->operator($t, ['facturador'], $t->branch);

        $data = $this->asUser($op)->getJson('/api/v1/sales')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($t->branch->id, $data[0]['branch_id']);
    }

    public function test_ventas_filtro_branch_id_no_amplia_alcance(): void
    {
        $t = $this->seedTenant('a');
        $this->makeSale($t, $t->branch);
        $this->makeSale($t, $t->branch2);
        $op = $this->operator($t, ['facturador'], $t->branch);

        // Intenta forzar la sucursal 2 por query string: NO debe ampliar.
        $data = $this->asUser($op)->getJson('/api/v1/sales?branch_id='.$t->branch2->id)->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($t->branch->id, $data[0]['branch_id']);
    }

    public function test_venta_detalle_de_otra_sucursal_rechazada(): void
    {
        $t = $this->seedTenant('a');
        $saleA2 = $this->makeSale($t, $t->branch2);
        $op = $this->operator($t, ['facturador'], $t->branch);

        $this->asUser($op)->getJson("/api/v1/sales/{$saleA2->id}")->assertStatus(403);
    }

    public function test_venta_detalle_de_otro_negocio_no_existe(): void
    {
        $t = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $saleB = $this->makeSale($b, $b->branch);
        $op = $this->operator($t, ['facturador'], $t->branch);

        $this->asUser($op)->getJson("/api/v1/sales/{$saleB->id}")->assertStatus(404); // BusinessScope lo oculta.
    }

    public function test_facturas_indice_solo_sucursal_del_operador(): void
    {
        $t = $this->seedTenant('a');
        $this->makeInvoice($t, $t->branch);
        $this->makeInvoice($t, $t->branch2);
        $op = $this->operator($t, ['cajero'], $t->branch); // cajero tiene facturas.ver.

        $data = $this->asUser($op)->getJson('/api/v1/invoices')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($t->branch->id, $data[0]['branch_id']);
    }

    // ============================================================
    //  B) Aislamiento de índice — recursos de sucursal INDIRECTA
    // ============================================================

    public function test_conteos_indice_solo_bodegas_asignadas(): void
    {
        $t = $this->seedTenant('a');
        $this->makePhysicalCount($t, $t->wh1); // S1 (se asignará)
        $this->makePhysicalCount($t, $t->wh2); // S2
        $op = $this->operator($t, ['bodeguero'], $t->branch);
        // Microcierre MOD-03: la visibilidad de conteos de ROL-03 exige asignación ACTIVA de la bodega.
        $this->assignWarehouse($t, $op, $t->wh1);

        $data = $this->asUser($op)->getJson('/api/v1/physical-counts')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($t->wh1->id, $data[0]['warehouse_id']);
    }

    public function test_conteo_detalle_bodeguero_bodega_asignada_ok_otra_rechazada(): void
    {
        $t = $this->seedTenant('a');
        $countA1 = $this->makePhysicalCount($t, $t->wh1);
        $countA2 = $this->makePhysicalCount($t, $t->wh2);
        $op = $this->operator($t, ['bodeguero'], $t->branch);
        $this->assignWarehouse($t, $op, $t->wh1);

        // El bodeguero (inventario.ver) abre el detalle de la bodega que tiene ASIGNADA.
        $this->asUser($op)->getJson("/api/v1/physical-counts/{$countA1->id}")->assertOk();
        // Pero NO el de una bodega que no tiene asignada (aquí, además, de otra sucursal).
        $this->asUser($op)->getJson("/api/v1/physical-counts/{$countA2->id}")->assertStatus(403);
    }

    public function test_traspasos_indice_involucran_su_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $wh1b = $this->makeWarehouse($t->business, $t->branch, 'B1b', false); // segunda bodega de S1 (no default).
        $this->makeStockTransfer($t, $t->wh1, $wh1b);                  // S1↔S1 (involucra S1).
        $wh2b = $this->makeWarehouse($t->business, $t->branch2, 'B2b', false);
        $this->makeStockTransfer($t, $t->wh2, $wh2b);                  // S2↔S2 (no involucra S1).
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        $data = $this->asUser($op)->getJson('/api/v1/stock-transfers')->assertOk()->json('data');
        $this->assertCount(1, $data); // solo el traspaso que toca S1.
    }

    // ============================================================
    //  C) Capacidades del BODEGUERO que antes eran inútiles
    // ============================================================

    public function test_bodeguero_lista_proveedores_pero_cajero_no(): void
    {
        $t = $this->seedTenant('a');
        $this->makeSupplier($t);

        $this->asUser($this->operator($t, ['bodeguero'], $t->branch))->getJson('/api/v1/suppliers')->assertOk();
        $this->asUser($this->operator($t, ['cajero'], $t->branch))->getJson('/api/v1/suppliers')->assertStatus(403);
    }

    public function test_bodeguero_lista_ordenes_de_su_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $this->makePurchaseOrder($t, $t->branch);
        $this->makePurchaseOrder($t, $t->branch2);
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        $data = $this->asUser($op)->getJson('/api/v1/purchase-orders')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($t->branch->id, $data[0]['branch_id']);
    }

    public function test_facturador_no_lista_ordenes_de_compra(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($this->operator($t, ['facturador'], $t->branch))
            ->getJson('/api/v1/purchase-orders')->assertStatus(403); // no tiene compras.ver.
    }

    public function test_orden_detalle_cross_branch_rechazada(): void
    {
        $t = $this->seedTenant('a');
        $orderA2 = $this->makePurchaseOrder($t, $t->branch2);
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        $this->asUser($op)->getJson("/api/v1/purchase-orders/{$orderA2->id}")->assertStatus(403);
    }

    public function test_bodeguero_lista_cxp_de_su_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $this->makeAccountPayable($t, $this->makePurchaseOrder($t, $t->branch));
        $this->makeAccountPayable($t, $this->makePurchaseOrder($t, $t->branch2));
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        $data = $this->asUser($op)->getJson('/api/v1/accounts-payable')->assertOk()->json('data');
        $this->assertCount(1, $data);
    }

    public function test_bodeguero_lista_devoluciones_y_notas_de_su_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $invA1 = $this->makeInvoice($t, $t->branch);
        $invA2 = $this->makeInvoice($t, $t->branch2);
        $retA1 = $this->makeSalesReturn($t, $t->branch, $invA1);
        $retA2 = $this->makeSalesReturn($t, $t->branch2, $invA2);
        $this->makeCreditNote($t, $invA1, $retA1);
        $this->makeCreditNote($t, $invA2, $retA2);
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        $returns = $this->asUser($op)->getJson('/api/v1/sales-returns')->assertOk()->json('data');
        $this->assertCount(1, $returns);
        $this->assertSame($t->branch->id, $returns[0]['branch_id']);

        $notes = $this->asUser($op)->getJson('/api/v1/credit-notes')->assertOk()->json('data');
        $this->assertCount(1, $notes); // solo la NC de la factura de S1.
    }

    // ============================================================
    //  D) Crear orden de compra (borrador) — BODEGUERO, acotado a su sucursal
    // ============================================================

    public function test_bodeguero_crea_orden_en_su_sucursal_pero_no_en_otra(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t);
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        $payload = fn (int $branchId) => [
            'supplier_id' => $supplier->id, 'branch_id' => $branchId, 'ordered_at' => now()->toDateString(),
            'items' => [['product_id' => $t->product->id, 'ordered_quantity' => '2.000', 'agreed_unit_cost' => '3.0000']],
        ];

        $this->asUser($op)->postJson('/api/v1/purchase-orders', $payload($t->branch->id))->assertStatus(201);
        $this->asUser($op)->postJson('/api/v1/purchase-orders', $payload($t->branch2->id))
            ->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_facturador_no_crea_orden_de_compra(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t);
        $op = $this->operator($t, ['facturador'], $t->branch);

        $this->asUser($op)->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id, 'branch_id' => $t->branch->id, 'ordered_at' => now()->toDateString(),
            'items' => [['product_id' => $t->product->id, 'ordered_quantity' => '2.000', 'agreed_unit_cost' => '3.0000']],
        ])->assertStatus(403); // no tiene compras.crear.
    }

    // ============================================================
    //  E) Potestades ROL-01/ROL-02 preservadas y sin aislamiento de sucursal
    // ============================================================

    public function test_admin_ve_todas_las_sucursales_en_indices(): void
    {
        $t = $this->seedTenant('a');
        $this->makeSale($t, $t->branch);
        $this->makeSale($t, $t->branch2);
        $this->makePurchaseOrder($t, $t->branch);
        $this->makePurchaseOrder($t, $t->branch2);

        $this->assertCount(2, $this->asUser($t->admin)->getJson('/api/v1/sales')->assertOk()->json('data'));
        $this->assertCount(2, $this->asUser($t->admin)->getJson('/api/v1/purchase-orders')->assertOk()->json('data'));
    }

    public function test_emitir_y_cancelar_orden_sigue_siendo_de_admin(): void
    {
        $t = $this->seedTenant('a');
        $order = $this->makePurchaseOrder($t, $t->branch);
        $order->forceFill(['status' => 'borrador'])->save();
        $op = $this->operator($t, ['bodeguero'], $t->branch);

        // El bodeguero puede verla/crearla, pero NO emitirla (potestad de ROL-02).
        $this->asUser($op)->postJson("/api/v1/purchase-orders/{$order->id}/issue")->assertStatus(403);
    }

    // ============================================================
    //  F) Backstop de SERVICE en mutaciones combinadas (invocación directa, sin FormRequest)
    // ============================================================

    public function test_service_traspaso_origen_cross_branch_rechazado_sin_filas(): void
    {
        $t = $this->seedTenant('a');
        $op = $this->operator($t, ['bodeguero'], $t->branch); // opera en S1.

        app(PermissionRegistrar::class)->setPermissionsTeamId($t->business->id);
        $operator = User::query()->whereKey($op->id)->firstOrFail();

        $threw = false;
        try {
            // Origen = bodega de S2 (ajena). Debe rechazarse aunque se omita el FormRequest.
            app(StockTransferService::class)->crear(
                $operator, $t->wh2->id, $t->wh1->id,
                [['product_id' => $t->product->id, 'quantity' => '1.000']], null,
            );
        } catch (AuthorizationException $e) {
            $threw = true;
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        $this->assertTrue($threw, 'El Service debe rechazar un traspaso con origen de otra sucursal.');
        $this->assertSame(0, StockTransfer::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_service_devolucion_factura_cross_branch_rechazada_sin_filas(): void
    {
        $t = $this->seedTenant('a');
        $invA2 = $this->makeInvoice($t, $t->branch2);      // factura de S2.
        $op = $this->operator($t, ['bodeguero'], $t->branch); // bodeguero de S1.

        $this->asUser($op); // ReturnService resuelve el actor por Auth (patrón existente).
        app(PermissionRegistrar::class)->setPermissionsTeamId($t->business->id);

        $threw = false;
        try {
            app(ReturnService::class)->registrar([
                'invoice_id' => $invA2->id,
                'lines' => [['sale_item_id' => 1, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']],
            ]);
        } catch (AuthorizationException $e) {
            $threw = true;
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        $this->assertTrue($threw, 'El Service debe rechazar una devolución de factura de otra sucursal.');
        $this->assertSame(0, SalesReturn::withoutGlobalScopes()->where('invoice_id', $invA2->id)->count());
    }
}
