<?php

declare(strict_types=1);

namespace Tests\Feature\Mod04;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\SupplierStatus;
use App\Models\AccountPayable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-04 - Pruebas HTTP end-to-end contra MySQL (datos controlados, revertidos).
 */
final class PurchaseFlowHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    /**
     * Autentica como $u para la siguiente peticion. Re-lee al usuario y limpia la
     * cache de permisos de Spatie para que el contexto de equipo/roles de una
     * peticion previa (otro usuario) no contamine la siguiente en el mismo proceso.
     * Artefacto de prueba (un proceso, varias peticiones); en produccion cada
     * peticion es un proceso nuevo.
     */
    private function asUser(User $u): static
    {
        // Reset completo del estado de autenticacion/tenant entre peticiones del
        // mismo proceso: guards resueltos, sesion (driver array retiene al usuario
        // anterior), team de Spatie y cache de permisos. En produccion cada
        // peticion es un proceso nuevo, por lo que esto es puramente de prueba.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        return $this->actingAs(User::query()->findOrFail($u->getKey()), 'web');
    }

    /** Contexto de un negocio ya sembrado. */
    private function seedTenant(string $slug): object
    {
        // Catalogo global de permisos + ROL-SYS (necesario para syncBusinessRoles).
        app(RolesAndPermissionsSeeder::class)->run();

        // Sin eventos: el aprovisionamiento del BusinessObserver (cliente generico,
        // secuencias, reglas de anomalia) no es necesario para las pruebas de MOD-04
        // y se omite por rapidez. Aqui solo se necesita la matriz de roles del
        // negocio, que se siembra a mano. (El observer quedo corregido y verificado
        // aparte en BusinessProvisioningTest.)
        $business = Business::withoutEvents(function () use ($slug): Business {
            $b = new Business([
                'name'     => 'Negocio '.$slug,
                'slug'     => $slug.'-'.(++self::$seq),
                'plan'     => 'basic',
                'status'   => 'active',
                'tax_rate' => '0.1500',
                'timezone' => 'America/Managua',
            ]);
            $b->save();

            return $b;
        });

        app(RolesAndPermissionsSeeder::class)->syncBusinessRoles($business->id);

        $owner    = $this->makeUser($business, RoleName::Owner);
        $admin    = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator);

        $category = new Category(['name' => 'Cat '.$slug]);
        $category->business_id = $business->id;
        $category->save();

        $unit = new UnitOfMeasure(['name' => 'Unidad', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $product = new Product([
            'category_id'      => $category->id,
            'unit_id'          => $unit->id,
            'sku'              => 'SKU-'.$slug.'-'.self::$seq,
            'name'             => 'Producto '.$slug,
            'type'             => ProductType::Simple,
            'sale_price'       => '20.00',
            'cost'             => '10.00',
            'tracks_inventory' => true,
            'is_taxable'       => true,
            'is_active'        => true,
        ]);
        $product->business_id = $business->id;
        $product->save();

        $branch = new Branch([
            'name'            => 'Sucursal '.$slug,
            'address'         => 'Dir 123',
            'manager_user_id' => $admin->id,
            'opened_at'       => '2026-01-01',
            'is_active'       => true,
        ]);
        $branch->business_id = $business->id;
        $branch->save();

        $warehouse = new Warehouse([
            'branch_id'  => $branch->id,
            'name'       => 'Bodega '.$slug,
            'is_default' => true,
            'is_active'  => true,
        ]);
        $warehouse->business_id = $business->id;
        $warehouse->save();

        return (object) compact('business', 'owner', 'admin', 'operator', 'category', 'unit', 'product', 'branch', 'warehouse');
    }

    private function makeUser(Business $business, RoleName $role): User
    {
        $user = new User([
            'name'      => $role->value.' '.(++self::$seq),
            'email'     => 'u'.self::$seq.'@test.local',
            'password'  => Hash::make('secret-Password-123'),
            'is_active' => true,
        ]);
        $user->business_id = $business->id;
        $user->save();

        // Asignacion de rol bajo el contexto de equipo (business_id) de Spatie.
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $user->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
    }

    private function makeSupplier(Business $business, bool $approved): Supplier
    {
        $supplier = new Supplier(['name' => 'Proveedor '.(++self::$seq)]);
        $supplier->business_id = $business->id;
        $supplier->status = $approved ? SupplierStatus::Aprobado : SupplierStatus::Pendiente;
        $supplier->save();

        return $supplier;
    }

    /** Crea y emite una orden (via HTTP como admin) para un proveedor aprobado. Devuelve [orderId, itemId]. */
    private function issuedOrder(object $t, Supplier $supplier): array
    {
        $create = $this->asUser($t->admin)->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id'   => $t->branch->id,
            'ordered_at'  => '2026-02-01',
            'items'       => [[
                'product_id'       => $t->product->id,
                'ordered_quantity' => '5.000',
                'agreed_unit_cost' => '10.0000',
            ]],
        ]);
        $create->assertCreated();
        $orderId = $create->json('data.id');
        $itemId  = $create->json('data.items.0.id');

        $this->asUser($t->admin)
            ->postJson("/api/v1/purchase-orders/{$orderId}/issue")
            ->assertOk()
            ->assertJsonPath('data.status', 'emitida');

        return [$orderId, $itemId];
    }

    /**
     * SETUP de un recibo discrepante (CxP congelada), vía servicio, no HTTP.
     *
     * El 409 HTTP de POST /goods-receipts se verifica aparte
     * (test_recepcion_discrepante_...). Aquí solo se necesita dejar el recibo
     * discrepante para probar resolve/pay. Usar el servicio evita un artefacto
     * de prueba: en un mismo proceso, la petición HTTP que sí renderiza el 409
     * deja la sesión/guard sucia y la SIGUIENTE petición pierde el usuario web
     * (no ocurre en producción, donde cada petición es un proceso nuevo).
     */
    private function createDiscrepantReceipt(object $t, int $orderId, int $itemId): int
    {
        try {
            app(\App\Services\Purchasing\GoodsReceiptService::class)->recibir(
                $t->operator,
                $orderId,
                $t->warehouse->id,
                [[
                    'purchase_order_item_id' => $itemId,
                    'received_quantity'      => '5.000',
                    'invoiced_unit_cost'     => '12.0000',
                ]],
                null,
                '60.00',
                '0',
            );
        } catch (\App\Exceptions\PurchaseMatchException $e) {
            return (int) $e->receipt->id;
        }

        $this->fail('Se esperaba una discrepancia de 3-Way Match.');
    }

    // -----------------------------------------------------------------------

    public function test_proveedor_aprobado_permite_crear_y_emitir_orden(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: false);

        // Aprobacion por ROL-01.
        $this->asUser($t->owner)
            ->postJson("/api/v1/suppliers/{$supplier->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'aprobado');

        [$orderId] = $this->issuedOrder($t, $supplier->fresh());

        $this->assertDatabaseHas('purchase_orders', ['id' => $orderId, 'status' => 'emitida']);
    }

    public function test_proveedor_no_aprobado_bloquea_orden_422(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: false); // pendiente

        // La barrera de validacion exige status=aprobado (422); el servicio revalida bajo lock.
        $this->asUser($t->admin)->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id'   => $t->branch->id,
            'ordered_at'  => '2026-02-01',
            'items'       => [[
                'product_id'       => $t->product->id,
                'ordered_quantity' => '5.000',
                'agreed_unit_cost' => '10.0000',
            ]],
        ])->assertStatus(422);
    }

    public function test_recepcion_correcta_ingresa_inventario_y_cxp_pendiente(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: true);
        [$orderId, $itemId] = $this->issuedOrder($t, $supplier);

        $resp = $this->asUser($t->operator)->postJson('/api/v1/goods-receipts', [
            'purchase_order_id'      => $orderId,
            'warehouse_id'           => $t->warehouse->id,
            'supplier_invoice_total' => '50.00',
            'tolerance'              => '0',
            'lines'                  => [[
                'purchase_order_item_id' => $itemId,
                'received_quantity'      => '5.000',
                'invoiced_unit_cost'     => '10.0000',
            ]],
        ]);

        $resp->assertCreated()
            ->assertJsonPath('data.match_status', 'ok')
            ->assertJsonPath('data.items.0.matched', true);

        $stock = StockLevel::withoutGlobalScopes()
            ->where('product_id', $t->product->id)
            ->where('warehouse_id', $t->warehouse->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertSame('5.000', (string) $stock->quantity);

        $this->assertTrue(
            InventoryMovement::withoutGlobalScopes()
                ->where('purchase_order_id', $orderId)->where('type', 'entrada')->exists()
        );

        $this->assertDatabaseHas('accounts_payable', [
            'purchase_order_id' => $orderId,
            'status'            => 'pendiente',
            'total_amount'      => '50.00',
        ]);

        $this->assertDatabaseHas('purchase_orders', ['id' => $orderId, 'status' => 'recibida']);
    }

    public function test_recepcion_discrepante_409_persiste_evidencia_sin_inventario(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: true);
        [$orderId, $itemId] = $this->issuedOrder($t, $supplier);

        $resp = $this->asUser($t->operator)->postJson('/api/v1/goods-receipts', [
            'purchase_order_id'      => $orderId,
            'warehouse_id'           => $t->warehouse->id,
            'supplier_invoice_total' => '60.00',
            'tolerance'              => '0',
            'lines'                  => [[
                'purchase_order_item_id' => $itemId,
                'received_quantity'      => '5.000',
                'invoiced_unit_cost'     => '12.0000',
            ]],
        ]);

        // 409 canonico de PurchaseMatchException::render():
        // { message, code:'PURCHASE_MATCH', data: GoodsReceiptResource }.
        $resp->assertStatus(409)
            ->assertJsonPath('code', 'PURCHASE_MATCH')
            ->assertJsonPath('data.match_status', 'discrepancia')
            ->assertJsonPath('data.items.0.matched', false)
            ->assertJsonPath('data.account_payable.status', 'congelada')
            ->assertJsonStructure(['message', 'code', 'data' => ['id', 'match_status', 'items', 'account_payable']]);

        $receiptId = $resp->json('data.id');
        $this->assertNotNull($receiptId, 'El 409 debe identificar el recurso creado.');

        $this->assertDatabaseHas('goods_receipts', ['id' => $receiptId, 'match_status' => 'discrepancia']);
        $this->assertDatabaseHas('goods_receipt_items', ['goods_receipt_id' => $receiptId, 'matched' => 0]);
        $this->assertDatabaseHas('accounts_payable', ['goods_receipt_id' => $receiptId, 'status' => 'congelada']);

        // NO ingreso a inventario.
        $this->assertFalse(
            StockLevel::withoutGlobalScopes()
                ->where('product_id', $t->product->id)->where('warehouse_id', $t->warehouse->id)->exists()
        );
        $this->assertFalse(
            InventoryMovement::withoutGlobalScopes()->where('purchase_order_id', $orderId)->exists()
        );
    }

    public function test_resolucion_aceptar_ingresa_inventario_y_descongela(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: true);
        [$orderId, $itemId] = $this->issuedOrder($t, $supplier);

        $receiptId = $this->createDiscrepantReceipt($t, $orderId, $itemId);

        $this->asUser($t->owner)
            ->postJson("/api/v1/goods-receipts/{$receiptId}/resolve", ['resolution' => 'aceptar'])
            ->assertOk()
            ->assertJsonPath('data.match_status', 'ok');

        $this->assertDatabaseHas('accounts_payable', ['goods_receipt_id' => $receiptId, 'status' => 'pendiente']);
        $this->assertTrue(
            StockLevel::withoutGlobalScopes()
                ->where('product_id', $t->product->id)->where('warehouse_id', $t->warehouse->id)->exists()
        );
    }

    public function test_resolucion_rechazar_bloquea_y_mantiene_cxp_congelada(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: true);
        [$orderId, $itemId] = $this->issuedOrder($t, $supplier);

        $receiptId = $this->createDiscrepantReceipt($t, $orderId, $itemId);

        $this->asUser($t->owner)
            ->postJson("/api/v1/goods-receipts/{$receiptId}/resolve", ['resolution' => 'rechazar'])
            ->assertOk()
            ->assertJsonPath('data.match_status', 'bloqueada');

        $this->assertDatabaseHas('accounts_payable', ['goods_receipt_id' => $receiptId, 'status' => 'congelada']);
        $this->assertFalse(
            StockLevel::withoutGlobalScopes()
                ->where('product_id', $t->product->id)->where('warehouse_id', $t->warehouse->id)->exists()
        );
    }

    public function test_pago_de_cxp_congelada_409(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: true);
        [$orderId, $itemId] = $this->issuedOrder($t, $supplier);
        $receiptId = $this->createDiscrepantReceipt($t, $orderId, $itemId);

        $payable = AccountPayable::withoutGlobalScopes()->where('goods_receipt_id', $receiptId)->firstOrFail();

        $this->asUser($t->admin)
            ->postJson("/api/v1/accounts-payable/{$payable->id}/pay", ['amount' => '10.00'])
            ->assertStatus(409);
    }

    public function test_sobrepago_de_cxp_pendiente_422(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: true);
        [$orderId, $itemId] = $this->issuedOrder($t, $supplier);

        // Recepcion correcta -> CxP pendiente total 50.00.
        $this->asUser($t->operator)->postJson('/api/v1/goods-receipts', [
            'purchase_order_id'      => $orderId,
            'warehouse_id'           => $t->warehouse->id,
            'supplier_invoice_total' => '50.00',
            'tolerance'              => '0',
            'lines'                  => [[
                'purchase_order_item_id' => $itemId,
                'received_quantity'      => '5.000',
                'invoiced_unit_cost'     => '10.0000',
            ]],
        ])->assertCreated();

        $payable = AccountPayable::withoutGlobalScopes()->where('purchase_order_id', $orderId)->firstOrFail();

        $this->asUser($t->admin)
            ->postJson("/api/v1/accounts-payable/{$payable->id}/pay", ['amount' => '60.00'])
            ->assertStatus(422);
    }

    public function test_filtros_y_paginacion_de_ordenes(): void
    {
        $t = $this->seedTenant('a');
        $supplier = $this->makeSupplier($t->business, approved: true);

        [$issuedId] = $this->issuedOrder($t, $supplier);

        // Segunda orden en borrador (solo crear).
        $this->asUser($t->admin)->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id'   => $t->branch->id,
            'ordered_at'  => '2026-02-02',
            'items'       => [[
                'product_id' => $t->product->id, 'ordered_quantity' => '1.000', 'agreed_unit_cost' => '10.0000',
            ]],
        ])->assertCreated();

        // Filtro status=emitida -> solo la emitida.
        $this->asUser($t->admin)->getJson('/api/v1/purchase-orders?status=emitida&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $issuedId)
            ->assertJsonPath('data.0.status', 'emitida');

        // Sin filtro -> 2 ordenes, con envelope de paginacion.
        $this->asUser($t->admin)->getJson('/api/v1/purchase-orders?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_aislamiento_entre_dos_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');

        $supplierA = $this->makeSupplier($a->business, approved: true);
        [$orderA] = $this->issuedOrder($a, $supplierA);

        // El admin de B no puede ver la orden de A (BusinessScope -> 404).
        $this->asUser($b->admin)
            ->getJson("/api/v1/purchase-orders/{$orderA}")
            ->assertNotFound();

        // El indice de B no incluye ordenes de A.
        $this->asUser($b->admin)->getJson('/api/v1/purchase-orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
