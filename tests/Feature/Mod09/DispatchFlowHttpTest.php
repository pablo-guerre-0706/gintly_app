<?php

declare(strict_types=1);

namespace Tests\Feature\Mod09;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-09 · Entregas y Retiros de Mercancía (HTTP e2e contra MySQL).
 *
 * Verifica el descuento físico atómico (único disparador del kardex de salida por venta),
 * el consumo de reserva, el saldo pendiente derivado, productos simples/compuestos/servicios,
 * la reversión con reingreso y re-reserva, el aislamiento por negocio y sucursal, las
 * políticas por rol, los filtros/paginación y la integridad del motor MySQL.
 */
final class DispatchFlowHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $authenticatedUser = User::query()->whereKey($u->getKey())->firstOrFail();

        if (! $authenticatedUser instanceof AuthenticatableContract) {
            throw new \RuntimeException('El usuario recargado no implementa Authenticatable.');
        }

        return $this->actingAs($authenticatedUser, 'web');
    }

    /**
     * Negocio con dos sucursales (cada una con su bodega predeterminada activa), catálogo
     * base, cliente real, caja abierta y operadores acotados por sucursal. El observer
     * siembra el cliente genérico y la regla fiscal estándar.
     */
    private function seedTenant(string $slug): object
    {
        $this->app['auth']->forgetGuards();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        app(RolesAndPermissionsSeeder::class)->run();

        $business = Business::create([
            'name'     => 'Negocio '.$slug,
            'slug'     => $slug.'-'.(++self::$seq),
            'plan'     => 'basic',
            'status'   => 'active',
            'tax_rate' => '0.1500',
            'timezone' => 'America/Managua',
        ]);

        $branch  = $this->makeBranch($business, 'S1 '.$slug);
        $branch2 = $this->makeBranch($business, 'S2 '.$slug);
        $warehouse  = $this->makeWarehouse($business, $branch, 'B1 '.$slug);
        $warehouse2 = $this->makeWarehouse($business, $branch2, 'B2 '.$slug);

        $owner     = $this->makeUser($business, RoleName::Owner);
        $admin     = $this->makeUser($business, RoleName::Admin);
        $operator  = $this->makeUser($business, RoleName::Operator, $branch);   // bodeguero de S1
        $operator2 = $this->makeUser($business, RoleName::Operator, $branch2);  // bodeguero de S2

        $category = new Category(['name' => 'Cat '.$slug]);
        $category->business_id = $business->id;
        $category->save();

        $unit = new UnitOfMeasure(['name' => 'Unidad', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $customer = new Customer([
            'name'            => 'Cliente '.$slug,
            'document_type'   => 'cedula',
            'document_number' => 'DOC-'.(++self::$seq),
            'credit_limit'    => '0.00',
        ]);
        $customer->business_id = $business->id;
        $customer->save();

        $register = new CashRegister();
        $register->forceFill([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Caja '.$slug,
            'is_active'   => true,
        ])->save();

        $session = new CashSession();
        $session->forceFill([
            'business_id'      => $business->id,
            'cash_register_id' => $register->id,
            'opened_by'        => $operator->id,
            'status'           => 'abierta',
            'opening_amount'   => '0.00',
            'opened_at'        => now(),
        ])->saveQuietly();

        return (object) compact(
            'business', 'owner', 'admin', 'operator', 'operator2',
            'branch', 'branch2', 'warehouse', 'warehouse2', 'category', 'unit', 'customer', 'session',
        );
    }

    private function makeUser(Business $business, RoleName $role, ?Branch $branch = null): User
    {
        $user = new User([
            'name'      => $role->value.' '.(++self::$seq),
            'email'     => 'u'.self::$seq.'@test.local',
            'password'  => Hash::make('secret-Password-123'),
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
        $user->business_id = $business->id;
        $user->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $user->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
    }

    private function makeBranch(Business $business, string $name): Branch
    {
        $branch = new Branch();
        $branch->forceFill([
            'business_id' => $business->id,
            'name'        => $name,
            'address'     => 'Dir. '.$name,
            'opened_at'   => now()->toDateString(),
            'is_active'   => true,
        ])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $business, Branch $branch, string $name): Warehouse
    {
        $warehouse = new Warehouse();
        $warehouse->forceFill([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => $name,
            'is_default'  => true,
            'is_active'   => true,
        ])->save();

        return $warehouse;
    }

    private function makeProduct(object $t, string $price, ProductType $type, TaxClass $class = TaxClass::Exempt): Product
    {
        $product = new Product([
            'category_id'      => $t->category->id,
            'unit_id'          => $t->unit->id,
            'sku'              => 'SKU-'.(++self::$seq),
            'name'             => 'Producto '.self::$seq,
            'type'             => $type,
            'sale_price'       => $price,
            'cost'             => '4.0000',
            'tracks_inventory' => $type !== ProductType::Service,
            'tax_class'        => $class->value,
            'is_active'        => true,
        ]);
        $product->business_id = $t->business->id;
        $product->save();

        return $product;
    }

    private function stockFor(object $t, Product $product, string $quantity, ?Warehouse $warehouse = null): void
    {
        $warehouse ??= $t->warehouse;
        $stock = new StockLevel();
        $stock->forceFill([
            'business_id'       => $t->business->id,
            'product_id'        => $product->id,
            'warehouse_id'      => $warehouse->id,
            'quantity'          => $quantity,
            'reserved_quantity' => '0.000',
            'average_cost'      => '4.0000',
        ])->save();
    }

    private function recipe(object $t, Product $compound, Product $ingredient, string $perUnit): void
    {
        $recipe = new ProductRecipe();
        $recipe->forceFill([
            'business_id'   => $t->business->id,
            'compound_id'   => $compound->id,
            'ingredient_id' => $ingredient->id,
            'quantity'      => $perUnit,
            'unit_id'       => $t->unit->id,
        ])->save();
    }

    /** Abre venta, agrega una línea y confirma. Devuelve [saleId, saleItemId]. */
    private function confirmedSale(object $t, Product $product, string $qty, ?Branch $branch = null): array
    {
        $branch ??= $t->branch;

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id'   => $branch->id,
            'customer_id' => $t->customer->id,
        ])->assertCreated()->json('data.id');

        $saleItemId = $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id' => $product->id,
            'quantity'   => $qty,
        ])->assertCreated()->json('data.id');

        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        return [(int) $saleId, (int) $saleItemId];
    }

    /** Emite una factura de contado (pago exacto en efectivo). Devuelve invoiceId. */
    private function invoiceContado(object $t, int $saleId, string $total): int
    {
        return (int) $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'payments'        => [['method' => 'efectivo', 'amount' => $total]],
        ])->assertCreated()->json('data.id');
    }

    /** Atajo: producto simple con stock, vendido y facturado. Devuelve [invoiceId, saleItemId, product]. */
    private function billedSimple(object $t, string $qty, string $price = '10.00', string $stock = '100.000'): array
    {
        $product = $this->makeProduct($t, $price, ProductType::Simple);
        $this->stockFor($t, $product, $stock);
        [$saleId, $saleItemId] = $this->confirmedSale($t, $product, $qty);
        $total = bcmul($price, $qty, 2);
        $invoiceId = $this->invoiceContado($t, $saleId, $total);

        return [$invoiceId, $saleItemId, $product];
    }

    private function stock(object $t, Product $product, ?Warehouse $warehouse = null): StockLevel
    {
        $warehouse ??= $t->warehouse;

        return StockLevel::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();
    }

    private function dispatch(object $t, int $invoiceId, array $lines, ?User $actor = null, string $receivedBy = 'Juan Receptor'): \Illuminate\Testing\TestResponse
    {
        $actor ??= $t->operator;

        return $this->asUser($actor)->postJson('/api/v1/dispatches', [
            'invoice_id'  => $invoiceId,
            'received_by' => $receivedBy,
            'lines'       => $lines,
        ]);
    }

    // =======================================================================
    // Registro del retiro
    // =======================================================================

    public function test_retiro_total_de_producto_simple(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()
            ->assertJsonPath('data.status', 'registrado')
            ->assertJsonPath('data.received_by', 'Juan Receptor')
            ->assertJsonPath('data.items.0.quantity', '5.000');

        $this->assertSame('5.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);
    }

    public function test_retiro_parcial_y_segundo_retiro_hasta_completar(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '2.000']])->assertCreated();
        $this->assertSame('2.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);

        // delivery-status: parcial.
        $this->asUser($t->admin)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")
            ->assertOk()->assertJsonPath('data.delivery_state', 'parcial');

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '3.000']])->assertCreated();
        $this->assertSame('5.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);

        $this->asUser($t->admin)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")
            ->assertOk()->assertJsonPath('data.delivery_state', 'completado');
    }

    public function test_sobre_retiro_devuelve_422_sin_mutaciones(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '5.000');

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '6.000']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DISPATCH_EXCEEDS_BALANCE')
            ->assertJsonPath('pending', '5.000')
            ->assertJsonPath('requested', '6.000');

        // Sin mutaciones: nada despachado, reserva intacta, sin kardex ni retiro.
        $this->assertSame('0.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);
        $this->assertSame('5.000', (string) $this->stock($t, $product)->reserved_quantity);
        $this->assertDatabaseCount('dispatches', 0);
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'salida']);
    }

    public function test_retiro_sobre_factura_anulada_da_409(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');

        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'Error de emisión'])->assertOk();

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '1.000']])
            ->assertStatus(409)
            ->assertJsonPath('code', 'DISPATCH_ON_VOIDED_INVOICE');

        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_linea_de_otra_factura_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceA] = $this->billedSimple($t, '5.000');
        [, $siB]    = $this->billedSimple($t, '5.000'); // línea de OTRA factura

        $this->dispatch($t, $invoiceA, [['sale_item_id' => $siB, 'quantity' => '1.000']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_DISPATCH_STATE');

        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_linea_duplicada_en_el_request_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');

        $this->dispatch($t, $invoiceId, [
            ['sale_item_id' => $siId, 'quantity' => '1.000'],
            ['sale_item_id' => $siId, 'quantity' => '1.000'],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.0.sale_item_id']);

        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_producto_servicio_no_es_despachable(): void
    {
        $t = $this->seedTenant('a');
        $service = $this->makeProduct($t, '20.00', ProductType::Service);
        [$saleId, $siId] = $this->confirmedSale($t, $service, '1.000');
        $invoiceId = $this->invoiceContado($t, $saleId, '20.00');

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '1.000']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_DISPATCH_STATE');
    }

    public function test_producto_no_inventariable_no_es_despachable(): void
    {
        $t = $this->seedTenant('a');

        // Producto simple pero SIN control de inventario (no genera retiro físico).
        $product = new Product([
            'category_id'      => $t->category->id,
            'unit_id'          => $t->unit->id,
            'sku'              => 'SKU-NOINV-'.(++self::$seq),
            'name'             => 'No inventariable '.self::$seq,
            'type'             => ProductType::Simple,
            'sale_price'       => '10.00',
            'cost'             => '4.0000',
            'tracks_inventory' => false,
            'tax_class'        => TaxClass::Exempt->value,
            'is_active'        => true,
        ]);
        $product->business_id = $t->business->id;
        $product->save();

        [$saleId, $siId] = $this->confirmedSale($t, $product, '2.000');
        $invoiceId = $this->invoiceContado($t, $saleId, '20.00');

        // Rechazo controlado al intentar despacharlo.
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '2.000']])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_DISPATCH_STATE');

        // Y queda EXCLUIDO del estado de entrega: sin líneas entregables ⇒ completado.
        $this->asUser($t->operator)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")
            ->assertOk()
            ->assertJsonPath('data.delivery_state', 'completado')
            ->assertJsonPath('data.lines.0.is_dispatchable', false);
    }

    public function test_producto_compuesto_usa_recipe_snapshot(): void
    {
        $t = $this->seedTenant('a');
        $ingredient = $this->makeProduct($t, '0.00', ProductType::Simple);
        $this->stockFor($t, $ingredient, '50.000');
        $compound = $this->makeProduct($t, '50.00', ProductType::Compound);
        $this->recipe($t, $compound, $ingredient, '2.000');

        [$saleId, $siId] = $this->confirmedSale($t, $compound, '3.000'); // reserva 6 de insumo
        $invoiceId = $this->invoiceContado($t, $saleId, '150.00');

        $this->assertSame('6.000', (string) $this->stock($t, $ingredient)->reserved_quantity);

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '3.000']])->assertCreated();

        // Descuenta insumo (no el compuesto), consume su reserva y asienta kardex con dispatch_id.
        $ingStock = $this->stock($t, $ingredient);
        $this->assertSame('44.000', (string) $ingStock->quantity);       // 50 - 6
        $this->assertSame('0.000', (string) $ingStock->reserved_quantity); // 6 - 6
        $this->assertSame('3.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $ingredient->id, 'type' => 'salida', 'quantity' => '6.000',
        ]);
    }

    public function test_descuenta_quantity_y_reserva_e_incrementa_despachado_con_kardex(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '4.000'); // stock 100, reserva 4

        $before = $this->stock($t, $product);
        $this->assertSame('100.000', (string) $before->quantity);
        $this->assertSame('4.000', (string) $before->reserved_quantity);

        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '4.000']])
            ->assertCreated()->json('data.id');

        $after = $this->stock($t, $product);
        $this->assertSame('96.000', (string) $after->quantity);           // -4
        $this->assertSame('0.000', (string) $after->reserved_quantity);   // -4 (consume reserva)
        $this->assertSame('4.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);

        // InventoryMovement de salida vinculado al dispatch.
        $this->assertDatabaseHas('inventory_movements', [
            'dispatch_id' => $dispatchId, 'product_id' => $product->id, 'type' => 'salida', 'quantity' => '4.000',
        ]);
    }

    public function test_stock_insuficiente_revierte_por_completo(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '5.000');

        // Merma externa simulada: existencia física por debajo de lo pendiente (mantiene reserved <= quantity).
        StockLevel::withoutGlobalScopes()
            ->where('product_id', $product->id)->where('warehouse_id', $t->warehouse->id)
            ->update(['quantity' => '4.000', 'reserved_quantity' => '4.000']);

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertStatus(409)
            ->assertJsonPath('error', 'INSUFFICIENT_STOCK');

        // Rollback íntegro.
        $this->assertDatabaseCount('dispatches', 0);
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'salida']);
        $this->assertSame('0.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);
        $stock = $this->stock($t, $product);
        $this->assertSame('4.000', (string) $stock->quantity);
        $this->assertSame('4.000', (string) $stock->reserved_quantity);
    }

    public function test_bodega_predeterminada_ausente_o_inactiva_da_error_controlado(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');

        // La bodega predeterminada se inactiva DESPUÉS de facturar.
        Warehouse::withoutGlobalScopes()->whereKey($t->warehouse->id)->update(['is_active' => false]);

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_DISPATCH_STATE');

        $this->assertDatabaseCount('dispatches', 0);
    }

    // =======================================================================
    // Aislamiento, roles y policies
    // =======================================================================

    public function test_aislamiento_de_sucursal_en_el_retiro(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000'); // factura en S1

        // operator2 pertenece a S2: no puede despachar una factura de S1 (403).
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '1.000']], $t->operator2)
            ->assertStatus(403);

        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_aislamiento_entre_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        [$invoiceId, $siId] = $this->billedSimple($a, '5.000');
        $dispatchId = $this->dispatch($a, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()->json('data.id');

        // El negocio B no ve ni revierte el retiro de A (BusinessScope → 404).
        $this->asUser($b->admin)->getJson("/api/v1/dispatches/{$dispatchId}")->assertNotFound();
        $this->asUser($b->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'x y z'])->assertNotFound();
        // Ni el saldo de entrega de una factura ajena.
        $this->asUser($b->admin)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")->assertNotFound();
    }

    public function test_roles_y_policies_de_retiro(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '2.000']])
            ->assertCreated()->json('data.id');

        // ROL-03 NO puede revertir (requiere ROL-02+).
        $this->asUser($t->operator)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Error operativo'])
            ->assertStatus(403);

        // ROL-02 sí revierte.
        $this->asUser($t->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Error operativo'])
            ->assertOk()->assertJsonPath('data.status', 'revertido');
    }

    // =======================================================================
    // Listado, show, items, delivery-status
    // =======================================================================

    public function test_listado_filtros_y_paginacion(): void
    {
        $t = $this->seedTenant('a');
        [$invA, $siA] = $this->billedSimple($t, '5.000');
        [$invB, $siB] = $this->billedSimple($t, '5.000');
        $this->dispatch($t, $invA, [['sale_item_id' => $siA, 'quantity' => '5.000']])->assertCreated();
        $this->dispatch($t, $invB, [['sale_item_id' => $siB, 'quantity' => '5.000']])->assertCreated();

        // Listado completo (ROL-02+): 2 retiros paginados.
        $this->asUser($t->admin)->getJson('/api/v1/dispatches')
            ->assertOk()->assertJsonPath('meta.total', 2);

        // Filtro por factura.
        $this->asUser($t->admin)->getJson("/api/v1/dispatches?invoice_id={$invA}")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.invoice_id', $invA);

        // Orden arbitrario rechazado (allowlist).
        $this->asUser($t->admin)->getJson('/api/v1/dispatches?sort=warehouse_id')
            ->assertStatus(422)->assertJsonValidationErrors(['sort']);

        // Paginación consistente.
        $this->asUser($t->admin)->getJson('/api/v1/dispatches?per_page=1')
            ->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonCount(1, 'data');
    }

    public function test_show_e_items_sin_datos_ajenos(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '5.000');
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()->json('data.id');

        $this->asUser($t->operator)->getJson("/api/v1/dispatches/{$dispatchId}")
            ->assertOk()
            ->assertJsonPath('data.id', $dispatchId)
            ->assertJsonPath('data.warehouse.id', $t->warehouse->id)
            ->assertJsonPath('data.invoice.id', $invoiceId);

        $this->asUser($t->operator)->getJson("/api/v1/dispatches/{$dispatchId}/items")
            ->assertOk()
            ->assertJsonPath('data.0.sale_item_id', $siId)
            ->assertJsonPath('data.0.quantity', '5.000')
            ->assertJsonPath('data.0.product.id', $product->id);
    }

    public function test_delivery_status_pendiente_parcial_completado(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '10.000');

        // Pendiente: nada retirado.
        $this->asUser($t->operator)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")
            ->assertOk()->assertJsonPath('data.delivery_state', 'pendiente')
            ->assertJsonPath('data.lines.0.pending_quantity', '10.000');

        // Parcial.
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '4.000']])->assertCreated();
        $this->asUser($t->operator)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")
            ->assertOk()->assertJsonPath('data.delivery_state', 'parcial');

        // Completado.
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '6.000']])->assertCreated();
        $this->asUser($t->operator)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")
            ->assertOk()->assertJsonPath('data.delivery_state', 'completado');
    }

    public function test_factura_solo_de_servicios_esta_completada(): void
    {
        $t = $this->seedTenant('a');
        $service = $this->makeProduct($t, '20.00', ProductType::Service);
        [$saleId] = $this->confirmedSale($t, $service, '1.000');
        $invoiceId = $this->invoiceContado($t, $saleId, '20.00');

        // Sin líneas entregables: completado (no hay mercancía pendiente).
        $this->asUser($t->operator)->getJson("/api/v1/invoices/{$invoiceId}/delivery-status")
            ->assertOk()->assertJsonPath('data.delivery_state', 'completado');
    }

    // =======================================================================
    // Reversión
    // =======================================================================

    public function test_reversion_completa_reingresa_y_re_reserva_simple(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '5.000');
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()->json('data.id');

        // Tras el retiro: quantity 95, reserved 0, despachado 5.
        $mid = $this->stock($t, $product);
        $this->assertSame('95.000', (string) $mid->quantity);
        $this->assertSame('0.000', (string) $mid->reserved_quantity);

        $this->asUser($t->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Entrega equivocada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revertido')
            ->assertJsonPath('data.revert_reason', 'Entrega equivocada')
            ->assertJsonPath('data.reverted_by', $t->admin->id);

        // Reingreso + RE-RESERVA (factura viva) + saldo pendiente restituido.
        $after = $this->stock($t, $product);
        $this->assertSame('100.000', (string) $after->quantity);          // +5
        $this->assertSame('5.000', (string) $after->reserved_quantity);   // re-reserva
        $this->assertSame('0.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);

        // Kardex de entrada vinculado + retiro conservado (no borrado).
        $this->assertDatabaseHas('inventory_movements', [
            'dispatch_id' => $dispatchId, 'product_id' => $product->id, 'type' => 'entrada', 'quantity' => '5.000',
        ]);
        $this->assertDatabaseHas('dispatches', ['id' => $dispatchId, 'status' => 'revertido']);
        $this->assertDatabaseHas('dispatch_items', ['dispatch_id' => $dispatchId]);
    }

    public function test_reversion_permite_volver_a_despachar_el_saldo_restituido(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '5.000');

        // Retiro total → reversión (restituye pendiente y re-reserva) → nuevo retiro total.
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()->json('data.id');
        $this->asUser($t->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Reintento'])->assertOk();

        // El saldo volvió a estar pendiente y re-reservado: un segundo retiro procede.
        $this->assertSame('0.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);
        $this->assertSame('5.000', (string) $this->stock($t, $product)->reserved_quantity);

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()->assertJsonPath('data.status', 'registrado');

        $this->assertSame('5.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);
        $this->assertSame('95.000', (string) $this->stock($t, $product)->quantity);   // vuelto a descontar
        $this->assertSame('0.000', (string) $this->stock($t, $product)->reserved_quantity);
        // Un retiro revertido + uno registrado coexisten (traza completa, sin borrado).
        $this->assertDatabaseCount('dispatches', 2);
    }

    public function test_reversion_repetida_da_409(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()->json('data.id');

        $this->asUser($t->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Primera'])->assertOk();
        $this->asUser($t->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Segunda'])
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_DISPATCH_STATE');
    }

    public function test_reversion_por_rol_no_autorizado_da_403(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])
            ->assertCreated()->json('data.id');

        $this->asUser($t->operator)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Intento'])
            ->assertStatus(403);
        $this->assertDatabaseHas('dispatches', ['id' => $dispatchId, 'status' => 'registrado']);
    }

    public function test_reversion_sobre_factura_anulada_da_409_sin_re_reserva(): void
    {
        $t = $this->seedTenant('a');
        // Retiro parcial para que la factura conserve saldo y sea anulable.
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '5.000');
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '2.000']])
            ->assertCreated()->json('data.id');

        // Anular la factura (libera solo el remanente no despachado; ver test dedicado).
        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'Anulación posterior'])->assertOk();

        $reservedBefore = (string) $this->stock($t, $product)->reserved_quantity;

        $this->asUser($t->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Intento tardío'])
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_DISPATCH_STATE');

        // No re-reserva: el retiro sigue registrado y la reserva no cambió.
        $this->assertDatabaseHas('dispatches', ['id' => $dispatchId, 'status' => 'registrado']);
        $this->assertSame($reservedBefore, (string) $this->stock($t, $product)->reserved_quantity);
    }

    public function test_reversion_de_compuesto_usa_snapshot(): void
    {
        $t = $this->seedTenant('a');
        $ingredient = $this->makeProduct($t, '0.00', ProductType::Simple);
        $this->stockFor($t, $ingredient, '50.000');
        $compound = $this->makeProduct($t, '50.00', ProductType::Compound);
        $this->recipe($t, $compound, $ingredient, '2.000');

        [$saleId, $siId] = $this->confirmedSale($t, $compound, '3.000');
        $invoiceId = $this->invoiceContado($t, $saleId, '150.00');
        $dispatchId = $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '3.000']])
            ->assertCreated()->json('data.id');

        // Tras el retiro: insumo 44, reserva 0.
        $this->assertSame('44.000', (string) $this->stock($t, $ingredient)->quantity);

        $this->asUser($t->admin)->postJson("/api/v1/dispatches/{$dispatchId}/revert", ['revert_reason' => 'Reingreso'])->assertOk();

        // Reingreso + re-reserva de INSUMOS según snapshot.
        $ing = $this->stock($t, $ingredient);
        $this->assertSame('50.000', (string) $ing->quantity);            // +6
        $this->assertSame('6.000', (string) $ing->reserved_quantity);    // re-reserva de insumos
        $this->assertDatabaseHas('inventory_movements', [
            'dispatch_id' => $dispatchId, 'product_id' => $ingredient->id, 'type' => 'entrada', 'quantity' => '6.000',
        ]);
    }

    // =======================================================================
    // Integración con anulación de factura (transversal MOD-07)
    // =======================================================================

    public function test_anulacion_libera_solo_el_remanente_no_despachado(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedSimple($t, '5.000'); // reserva 5

        // Retiro parcial de 2: quantity 98, reserved 3, despachado 2.
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '2.000']])->assertCreated();
        $this->assertSame('3.000', (string) $this->stock($t, $product)->reserved_quantity);

        // Anular: libera SOLO el remanente no despachado (3), nunca lo ya entregado (2).
        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'Anulación con entrega parcial'])->assertOk();

        $after = $this->stock($t, $product);
        $this->assertSame('98.000', (string) $after->quantity);          // no reingresa lo entregado
        $this->assertSame('0.000', (string) $after->reserved_quantity);  // libera solo el remanente (3)
    }

    // =======================================================================
    // Concurrencia / atomicidad
    // =======================================================================

    public function test_dos_retiros_no_superan_el_saldo_pendiente(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedSimple($t, '5.000');

        // Primer retiro consume 4; el segundo pide 2 (pendiente 1) → 422 sin mutación.
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '4.000']])->assertCreated();
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '2.000']])
            ->assertStatus(422)->assertJsonPath('code', 'DISPATCH_EXCEEDS_BALANCE');

        $this->assertSame('4.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->dispatched_quantity);
        $this->assertDatabaseCount('dispatches', 1);
    }

    public function test_codigo_secuencial_unico_por_negocio(): void
    {
        $t = $this->seedTenant('a');
        [$invA, $siA] = $this->billedSimple($t, '5.000');
        [$invB, $siB] = $this->billedSimple($t, '5.000');

        $codeA = $this->dispatch($t, $invA, [['sale_item_id' => $siA, 'quantity' => '5.000']])->assertCreated()->json('data.code');
        $codeB = $this->dispatch($t, $invB, [['sale_item_id' => $siB, 'quantity' => '5.000']])->assertCreated()->json('data.code');

        $this->assertNotSame($codeA, $codeB);
        $this->assertStringStartsWith('D-', $codeA);
        $this->assertSame(2, DB::table('dispatches')->where('business_id', $t->business->id)->distinct()->count('code'));
    }

    public function test_rollback_integral_ante_fallo_intermedio(): void
    {
        $t = $this->seedTenant('a');
        // Dos líneas: la 1ª válida, la 2ª excede su saldo → toda la operación se revierte.
        $p1 = $this->makeProduct($t, '10.00', ProductType::Simple);
        $this->stockFor($t, $p1, '100.000');
        $p2 = $this->makeProduct($t, '10.00', ProductType::Simple);
        $this->stockFor($t, $p2, '100.000');

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id' => $t->branch->id, 'customer_id' => $t->customer->id,
        ])->assertCreated()->json('data.id');
        $si1 = $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", ['product_id' => $p1->id, 'quantity' => '5.000'])->json('data.id');
        $si2 = $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", ['product_id' => $p2->id, 'quantity' => '5.000'])->json('data.id');
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();
        $invoiceId = $this->invoiceContado($t, $saleId, '100.00');

        $this->dispatch($t, $invoiceId, [
            ['sale_item_id' => $si1, 'quantity' => '5.000'],   // válida
            ['sale_item_id' => $si2, 'quantity' => '6.000'],   // excede saldo (5)
        ])->assertStatus(422)->assertJsonPath('code', 'DISPATCH_EXCEEDS_BALANCE');

        // NADA se persiste: ni retiro, ni líneas, ni kardex, ni despachado en la 1ª línea.
        $this->assertDatabaseCount('dispatches', 0);
        $this->assertDatabaseCount('dispatch_items', 0);
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'salida']);
        $this->assertSame('0.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($si1)->dispatched_quantity);
        $this->assertSame('5.000', (string) $this->stock($t, $p1)->reserved_quantity); // reserva intacta
    }

    // =======================================================================
    // Integridad del motor MySQL (information_schema)
    // =======================================================================

    public function test_integridad_de_esquema_mysql(): void
    {
        $db = DB::getDatabaseName();

        // dispatched_quantity: decimal(_,3), CHECK >= 0 y <= quantity.
        $col = DB::selectOne(
            'SELECT DATA_TYPE, NUMERIC_SCALE AS s FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'sale_items', 'dispatched_quantity'],
        );
        $this->assertNotNull($col);
        $this->assertSame('decimal', strtolower((string) $col->DATA_TYPE));
        $this->assertSame(3, (int) $col->s);

        $this->assertCheckExists($db, 'sale_items', 'chk_sale_item_dispatch_not_exceed');
        $this->assertCheckExists($db, 'sale_items', 'chk_sale_item_dispatched_non_negative');
        $this->assertCheckExists($db, 'dispatches', 'chk_dispatch_revert_coherence');
        $this->assertCheckExists($db, 'dispatch_items', 'chk_dispatch_item_quantity');

        // UNIQUE(business_id, code).
        $unique = DB::selectOne(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'dispatches', 'uniq_dispatch_code', 'code'],
        );
        $this->assertNotNull($unique);
        $this->assertSame(0, (int) $unique->NON_UNIQUE);

        // received_by NOT NULL (receptor declarado).
        $receivedBy = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'dispatches', 'received_by'],
        );
        $this->assertSame('NO', strtoupper((string) $receivedBy->IS_NULLABLE));

        // Índices requeridos.
        foreach (['idx_dispatch_status', 'idx_dispatch_invoice', 'idx_dispatch_warehouse', 'idx_dispatch_dispatched_at'] as $index) {
            $this->assertTrue($this->indexExists($db, 'dispatches', $index), "Falta el índice {$index}.");
        }

        // FK dispatch_items → dispatches (cascade) y sale_items (restrict); FK movimientos.dispatch_id (restrict).
        $this->assertSame('CASCADE', $this->deleteRule($db, 'dispatch_items', 'dispatch_id'));
        $this->assertSame('RESTRICT', $this->deleteRule($db, 'dispatch_items', 'sale_item_id'));
        $this->assertSame('RESTRICT', $this->deleteRule($db, 'inventory_movements', 'dispatch_id'));
    }

    private function assertCheckExists(string $db, string $table, string $constraint): void
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ? AND CONSTRAINT_NAME = ?',
            [$db, $table, 'CHECK', $constraint],
        );
        $this->assertSame(1, (int) $row->c, "Falta el CHECK {$constraint} en {$table}.");
    }

    private function indexExists(string $db, string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$db, $table, $index],
        )->c > 0;
    }

    private function deleteRule(string $db, string $table, string $column): string
    {
        $row = DB::selectOne(
            "SELECT rc.DELETE_RULE AS del
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = ? AND rc.TABLE_NAME = ? AND kcu.COLUMN_NAME = ?",
            [$db, $table, $column],
        );

        return strtoupper((string) $row->del);
    }
}
