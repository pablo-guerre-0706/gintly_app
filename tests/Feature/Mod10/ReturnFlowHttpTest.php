<?php

declare(strict_types=1);

namespace Tests\Feature\Mod10;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\AccountReceivable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\StockLevel;
use App\Models\TaxRule;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-10 · Devoluciones, Reingresos y Mermas (HTTP e2e contra MySQL).
 *
 * Verifica el cálculo fiscal EXCLUSIVO desde los snapshots congelados en MOD-07, el reingreso
 * vs merma, la unicidad de la nota de crédito, la ramificación del resarcimiento (reducción de
 * CxC, saldo a favor, reembolso en efectivo con ROL-01 y caja), la atomicidad con rollback y
 * el aislamiento por negocio.
 */
final class ReturnFlowHttpTest extends MysqlTestCase
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

        $branch    = $this->makeBranch($business, 'S1 '.$slug);
        $branch2   = $this->makeBranch($business, 'S2 '.$slug);
        $warehouse = $this->makeWarehouse($business, $branch, 'B1 '.$slug);
        $this->makeWarehouse($business, $branch2, 'B2 '.$slug);

        $owner    = $this->makeUser($business, RoleName::Owner);
        $admin    = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator, $branch);

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
            'credit_limit'    => '100000.00',
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
            'business', 'owner', 'admin', 'operator',
            'branch', 'branch2', 'warehouse', 'category', 'unit', 'customer', 'register', 'session',
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

    private function openSale(object $t): int
    {
        return (int) $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id'   => $t->branch->id,
            'customer_id' => $t->customer->id,
        ])->assertCreated()->json('data.id');
    }

    private function addItem(object $t, int $saleId, Product $product, string $qty, string $discount = '0.00'): int
    {
        return (int) $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id'      => $product->id,
            'quantity'        => $qty,
            'discount_amount' => $discount,
        ])->assertCreated()->json('data.id');
    }

    private function confirm(object $t, int $saleId): void
    {
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();
    }

    private function invoiceContado(object $t, int $saleId, string $total): int
    {
        return (int) $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'payments'        => [['method' => 'efectivo', 'amount' => $total]],
        ])->assertCreated()->json('data.id');
    }

    private function invoiceCredito(object $t, int $saleId): int
    {
        return (int) $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'     => [$saleId],
            'payment_type' => 'credito',
        ])->assertCreated()->json('data.id');
    }

    private function dispatch(object $t, int $invoiceId, array $lines, ?User $actor = null): TestResponse
    {
        return $this->asUser($actor ?? $t->operator)->postJson('/api/v1/dispatches', [
            'invoice_id'  => $invoiceId,
            'received_by' => 'Receptor',
            'lines'       => $lines,
        ]);
    }

    private function returnRequest(object $t, array $payload, ?User $actor = null): TestResponse
    {
        return $this->asUser($actor ?? $t->operator)->postJson('/api/v1/sales-returns', $payload);
    }

    /**
     * Atajo: producto simple exento con stock, facturado (contado o crédito) y despachado por completo.
     * Devuelve [invoiceId, saleItemId, product].
     */
    private function billedDispatched(object $t, string $qty, string $price = '10.00', bool $credit = false, string $stock = '100.000'): array
    {
        $product = $this->makeProduct($t, $price, ProductType::Simple);
        $this->stockFor($t, $product, $stock);
        $saleId = $this->openSale($t);
        $siId   = $this->addItem($t, $saleId, $product, $qty);
        $this->confirm($t, $saleId);

        $invoiceId = $credit
            ? $this->invoiceCredito($t, $saleId)
            : $this->invoiceContado($t, $saleId, bcmul($price, $qty, 2));

        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => $qty]])->assertCreated();

        return [$invoiceId, $siId, $product];
    }

    private function stock(object $t, Product $product, ?Warehouse $warehouse = null): StockLevel
    {
        $warehouse ??= $t->warehouse;

        return StockLevel::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();
    }

    private function arFor(int $invoiceId): AccountReceivable
    {
        return AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoiceId)->firstOrFail();
    }

    // =======================================================================
    // Cantidad devolvible, parcial/total, sobre-devolución
    // =======================================================================

    public function test_devolucion_parcial_y_total(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true);

        // Parcial (2 de 5).
        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated()->assertJsonPath('data.status', 'procesada');
        $this->assertSame('2.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->returned_quantity);

        // Total del remanente (3).
        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '3.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated();
        $this->assertSame('5.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->returned_quantity);
    }

    public function test_multiples_devoluciones_hasta_agotar_saldo(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true);

        foreach (['2.000', '2.000', '1.000'] as $q) {
            $this->returnRequest($t, [
                'invoice_id' => $invoiceId,
                'lines'      => [['sale_item_id' => $siId, 'quantity' => $q, 'reason_code' => 'error_despacho']],
            ])->assertCreated();
        }

        // Agotado: un abono más excede lo devolvible (0).
        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'error_despacho']],
        ])->assertStatus(422)->assertJsonPath('code', 'RETURN_QUANTITY');

        $this->assertSame(3, SalesReturn::withoutGlobalScopes()->where('invoice_id', $invoiceId)->count());
    }

    public function test_sobre_devolucion_422_sin_mutaciones(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedDispatched($t, '5.000', credit: true);

        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '6.000', 'reason_code' => 'insatisfaccion']],
        ])->assertStatus(422)
            ->assertJsonPath('code', 'RETURN_QUANTITY')
            ->assertJsonPath('returnable', '5.000')
            ->assertJsonPath('requested', '6.000');

        $this->assertSame('0.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->returned_quantity);
        $this->assertDatabaseCount('sales_returns', 0);
        $this->assertDatabaseCount('credit_notes', 0);
        // Stock intacto (quantity tras despacho 95, sin reingreso).
        $this->assertSame('95.000', (string) $this->stock($t, $product)->quantity);
    }

    public function test_devolucion_de_mercancia_no_retirada_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        // Facturada pero NO despachada (dispatched_quantity 0 ⇒ devolvible 0).
        $product = $this->makeProduct($t, '10.00', ProductType::Simple);
        $this->stockFor($t, $product, '100.000');
        $saleId = $this->openSale($t);
        $siId   = $this->addItem($t, $saleId, $product, '5.000');
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);

        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']],
        ])->assertStatus(422)->assertJsonPath('code', 'RETURN_QUANTITY')->assertJsonPath('returnable', '0.000');
    }

    public function test_linea_ajena_a_la_factura_y_linea_duplicada(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceA, $siA] = $this->billedDispatched($t, '5.000', credit: true);
        [, $siB]          = $this->billedDispatched($t, '5.000', credit: true);

        // Línea de otra factura.
        $this->returnRequest($t, [
            'invoice_id' => $invoiceA,
            'lines'      => [['sale_item_id' => $siB, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']],
        ])->assertStatus(422)->assertJsonPath('code', 'RETURN_QUANTITY');

        // Línea duplicada en el request.
        $this->returnRequest($t, [
            'invoice_id' => $invoiceA,
            'lines'      => [
                ['sale_item_id' => $siA, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion'],
                ['sale_item_id' => $siA, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.0.sale_item_id']);

        $this->assertDatabaseCount('sales_returns', 0);
    }

    // =======================================================================
    // Reingreso, merma, compuestos, servicios
    // =======================================================================

    public function test_reingreso_correcto_al_inventario(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedDispatched($t, '5.000', credit: true); // stock 95 tras despacho

        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion', 'destination' => 'reingreso']],
        ])->assertCreated();

        // Reingreso a stock vendible (no re-reserva): quantity 95 + 2 = 97.
        $stock = $this->stock($t, $product);
        $this->assertSame('97.000', (string) $stock->quantity);
        $this->assertSame('0.000', (string) $stock->reserved_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id, 'type' => 'entrada', 'quantity' => '2.000',
        ]);
    }

    public function test_merma_no_reingresa_stock_vendible(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedDispatched($t, '5.000', credit: true);

        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'vencido', 'destination' => 'merma']],
        ])->assertCreated();

        // Merma: NO vuelve al stock vendible; queda como ajuste de pérdida trazable.
        $this->assertSame('95.000', (string) $this->stock($t, $product)->quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['type' => 'merma']);
        $this->assertSame('2.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->returned_quantity);
    }

    public function test_motivo_y_destino_incompatibles_se_rechazan(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true);

        // 'vencido' NUNCA reingresa.
        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'vencido', 'destination' => 'reingreso']],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.0.destination']);
    }

    public function test_producto_compuesto_usa_recipe_snapshot(): void
    {
        $t = $this->seedTenant('a');
        $ingredient = $this->makeProduct($t, '0.00', ProductType::Simple);
        $this->stockFor($t, $ingredient, '50.000');
        $compound = $this->makeProduct($t, '50.00', ProductType::Compound);
        $this->recipe($t, $compound, $ingredient, '2.000');

        $saleId = $this->openSale($t);
        $siId   = $this->addItem($t, $saleId, $compound, '3.000'); // reserva 6 de insumo
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '3.000']])->assertCreated();

        // Tras despacho: insumo 44 (50-6).
        $this->assertSame('44.000', (string) $this->stock($t, $ingredient)->quantity);

        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '3.000', 'reason_code' => 'insatisfaccion', 'destination' => 'reingreso']],
        ])->assertCreated();

        // Reingreso de INSUMOS según snapshot: 44 + 6 = 50.
        $this->assertSame('50.000', (string) $this->stock($t, $ingredient)->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $ingredient->id, 'type' => 'entrada', 'quantity' => '6.000',
        ]);
    }

    public function test_servicio_no_es_devolvible_por_no_tener_movimiento_fisico(): void
    {
        $t = $this->seedTenant('a');
        $service = $this->makeProduct($t, '20.00', ProductType::Service);
        $saleId  = $this->openSale($t);
        $siId    = $this->addItem($t, $saleId, $service, '1.000');
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);

        // Nunca se despachó (servicio) ⇒ devolvible 0 ⇒ no genera movimiento físico.
        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']],
        ])->assertStatus(422)->assertJsonPath('code', 'RETURN_QUANTITY')->assertJsonPath('returnable', '0.000');
    }

    // =======================================================================
    // Nota de crédito: unicidad, folio, cálculo fiscal desde snapshot
    // =======================================================================

    public function test_nota_de_credito_unica_por_devolucion(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true);

        $returnId = $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated()->json('data.id');

        $this->assertSame(1, CreditNote::withoutGlobalScopes()->where('sales_return_id', $returnId)->count());

        // El motor impide una 2ª NC para la misma devolución (uniq_credit_note_return).
        $existing = CreditNote::withoutGlobalScopes()->where('sales_return_id', $returnId)->firstOrFail();
        try {
            DB::table('credit_notes')->insert([
                'business_id'     => $existing->business_id,
                'invoice_id'      => $existing->invoice_id,
                'sales_return_id' => $existing->sales_return_id, // duplicado deliberado
                'customer_id'     => $existing->customer_id,
                'issued_by'       => $existing->issued_by,
                'folio'           => 'NC-DUP-'.self::$seq,
                'resolution_type' => 'reduccion_cxc',
                'total_amount'    => '1.00',
                'tax_amount'      => '0.00',
                'status'          => 'emitida',
                'issued_at'       => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $this->fail('El motor debía rechazar dos NC para la misma devolución.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(1062, (int) ($e->errorInfo[1] ?? 0));
        }
    }

    public function test_folio_secuencial_unico(): void
    {
        $t = $this->seedTenant('a');
        [$invA, $siA] = $this->billedDispatched($t, '5.000', credit: true);
        [$invB, $siB] = $this->billedDispatched($t, '5.000', credit: true);

        $rA = $this->returnRequest($t, ['invoice_id' => $invA, 'lines' => [['sale_item_id' => $siA, 'quantity' => '1.000', 'reason_code' => 'otro']]])
            ->assertCreated();
        $rB = $this->returnRequest($t, ['invoice_id' => $invB, 'lines' => [['sale_item_id' => $siB, 'quantity' => '1.000', 'reason_code' => 'otro']]])
            ->assertCreated();

        $this->assertNotSame($rA->json('data.code'), $rB->json('data.code'));
        $this->assertStringStartsWith('DV-', $rA->json('data.code'));
        $this->assertNotSame($rA->json('data.credit_note.folio'), $rB->json('data.credit_note.folio'));
        $this->assertStringStartsWith('NC-', $rA->json('data.credit_note.folio'));
    }

    public function test_calculo_fiscal_proporcional_desde_snapshot_original(): void
    {
        $t = $this->seedTenant('a');
        // Producto gravado estándar 15 % (regla sembrada por el observer): por unidad → neto 100, IVA 15.
        $product = $this->makeProduct($t, '100.00', ProductType::Simple, TaxClass::Standard);
        $this->stockFor($t, $product, '100.000');
        $saleId = $this->openSale($t);
        $siId   = $this->addItem($t, $saleId, $product, '5.000');
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '5.000']])->assertCreated();

        // La tasa vigente cambia DESPUÉS a 20 %: la NC NO debe usarla.
        $rule = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')->whereNull('branch_id')->where('is_active', true)->firstOrFail();
        $this->asUser($t->owner)->putJson("/api/v1/tax-rules/{$rule->id}", ['rate' => '0.200000'])->assertOk();

        // Devolución de 2 de 5: neto 200, IVA congelado 30 (=15×2), NC 230. NO 240 (con 20 %).
        $resp = $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated();

        $this->assertSame('200.00', $resp->json('data.total_returned'));
        $this->assertSame('30.00', $resp->json('data.credit_note.tax_amount'));
        $this->assertSame('230.00', $resp->json('data.credit_note.total_amount'));
    }

    // =======================================================================
    // Resarcimiento: CxC, saldo a favor, reembolso efectivo
    // =======================================================================

    public function test_reduccion_prioritaria_de_cxc_en_credito(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true); // CxC balance 50
        $ar = $this->arFor($invoiceId);
        $this->assertSame('50.00', (string) $ar->balance);

        $resp = $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated()->assertJsonPath('data.credit_note.resolution_type', 'reduccion_cxc');

        // NC 20 (exento) reduce la CxC: 50 → 30.
        $this->assertSame('20.00', $resp->json('data.credit_note.total_amount'));
        $this->assertSame('30.00', (string) $ar->refresh()->balance);
    }

    public function test_conserva_abonos_historicos_al_reducir_cxc(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true); // CxC 50
        $ar = $this->arFor($invoiceId);

        // Abono de 20 (transferencia, sin caja).
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '20.00', 'payment_method' => 'transferencia',
        ])->assertCreated();
        $this->assertSame('30.00', (string) $ar->refresh()->balance);

        // Devolución total (50) reduce la CxC conservando el abono.
        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '5.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated();

        // total baja a lo abonado (20) como piso; abono preservado.
        $ar->refresh();
        $this->assertSame('20.00', (string) $ar->paid_amount);
        $this->assertSame('0.00', (string) $ar->balance);
        $this->assertSame(1, DB::table('receivable_payments')->where('accounts_receivable_id', $ar->id)->count());
    }

    public function test_reembolso_efectivo_con_sesion_y_rol01(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000'); // CONTADO pagado 50

        $resp = $this->returnRequest($t, [
            'invoice_id'      => $invoiceId,
            'cash_session_id' => $t->session->id,
            'lines'           => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ], $t->owner)->assertCreated()->assertJsonPath('data.credit_note.resolution_type', 'reembolso_efectivo');

        $this->assertSame('20.00', $resp->json('data.credit_note.total_amount'));
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'egreso_autorizado', 'amount' => '20.00', 'authorized_by' => $t->owner->id,
        ]);
    }

    public function test_reembolso_efectivo_sin_autorizacion_rol01_da_403(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000'); // contado

        // ROL-03 con caja: la resolución es efectivo pero carece de autoridad ROL-01.
        $this->returnRequest($t, [
            'invoice_id'      => $invoiceId,
            'cash_session_id' => $t->session->id,
            'lines'           => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ], $t->operator)->assertStatus(403)->assertJsonPath('code', 'REFUND_AUTHORIZATION_REQUIRED');

        $this->assertDatabaseCount('sales_returns', 0);
        $this->assertDatabaseMissing('cash_movements', ['category' => 'egreso_autorizado']);
    }

    public function test_reembolso_efectivo_sobre_credito_no_pagado_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true); // crédito con saldo

        // Aportar caja sobre un crédito no pagado ⇒ no se reembolsa lo no pagado.
        $this->returnRequest($t, [
            'invoice_id'      => $invoiceId,
            'cash_session_id' => $t->session->id,
            'lines'           => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ], $t->owner)->assertStatus(422)->assertJsonPath('code', 'INVALID_REFUND_METHOD');

        $this->assertDatabaseCount('sales_returns', 0);
    }

    public function test_reembolso_efectivo_sin_caja_activa_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000'); // contado

        // Sesión cerrada aportada como caja del reembolso.
        $closed = new CashSession();
        $closed->forceFill([
            'business_id' => $t->business->id, 'cash_register_id' => $t->register->id,
            'opened_by' => $t->operator->id, 'status' => 'cerrada', 'opening_amount' => '0.00',
            'opened_at' => now()->subHour(), 'closed_at' => now(),
        ])->saveQuietly();

        $this->returnRequest($t, [
            'invoice_id'      => $invoiceId,
            'cash_session_id' => $closed->id,
            'lines'           => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ], $t->owner)->assertStatus(422)->assertJsonPath('code', 'INVALID_REFUND_METHOD');

        $this->assertDatabaseCount('sales_returns', 0);
    }

    public function test_saldo_a_favor_cuando_no_hay_caja(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000'); // contado pagado

        // Sin caja aportada ⇒ saldo a favor (NC de saldo).
        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ], $t->owner)->assertCreated()->assertJsonPath('data.credit_note.resolution_type', 'nota_credito_saldo');

        // El saldo a favor del cliente lo refleja el endpoint dedicado.
        $this->asUser($t->admin)->getJson("/api/v1/customers/{$t->customer->id}/credit-balance")
            ->assertOk()
            ->assertJsonPath('data.available_credit_balance', '20.00')
            ->assertJsonPath('data.open_credit_notes.0.total_amount', '20.00');
    }

    public function test_devoluciones_parciales_acumuladas_con_residuos_de_redondeo(): void
    {
        $t = $this->seedTenant('a');
        // Línea con cantidad, descuento e impuesto que producen residuos decimales:
        // precio 33.33 × 3 = 99.99 − descuento 10.00 = line_total 89.99; IVA 15% = 13.50; base 89.99.
        $product = $this->makeProduct($t, '33.33', ProductType::Simple, TaxClass::Standard);
        $this->stockFor($t, $product, '100.000');
        $saleId = $this->openSale($t);
        $siId   = $this->addItem($t, $saleId, $product, '3.000', '10.00');
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '3.000']])->assertCreated();

        // Figuras congeladas originales.
        $frozen = SaleItem::withoutGlobalScopes()->findOrFail($siId);
        $this->assertSame('89.99', (string) $frozen->line_total);
        $this->assertSame('13.50', (string) $frozen->tax_amount);
        $this->assertSame('89.99', (string) $frozen->taxable_base);

        // Cambiar la tasa vigente a 20% DESPUÉS de facturar no debe alterar ningún cálculo.
        $rule = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')->whereNull('branch_id')->where('is_active', true)->firstOrFail();
        $this->asUser($t->owner)->putJson("/api/v1/tax-rules/{$rule->id}", ['rate' => '0.200000'])->assertOk();

        // Tres parciales de 1: los netos acumulan con residuo; el último lo absorbe.
        $p1 = $this->returnRequest($t, ['invoice_id' => $invoiceId, 'lines' => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']]])->assertCreated();
        $p2 = $this->returnRequest($t, ['invoice_id' => $invoiceId, 'lines' => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']]])->assertCreated();
        $p3 = $this->returnRequest($t, ['invoice_id' => $invoiceId, 'lines' => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']]])->assertCreated();

        // Netos: 30.00, 29.99 (residuo), 30.00 → suma 89.99 exacta. IVA 4.50 c/u → 13.50.
        $this->assertSame('30.00', $p1->json('data.total_returned'));
        $this->assertSame('29.99', $p2->json('data.total_returned'));
        $this->assertSame('30.00', $p3->json('data.total_returned'));
        $this->assertSame('4.50', $p1->json('data.credit_note.tax_amount'));
        $this->assertSame('4.50', $p2->json('data.credit_note.tax_amount'));
        $this->assertSame('4.50', $p3->json('data.credit_note.tax_amount'));

        // Cada NC se calculó desde el snapshot (IVA 4.50, no 20%): totales 34.50 / 34.49 / 34.50.
        $this->assertSame('34.50', $p1->json('data.credit_note.total_amount'));
        $this->assertSame('34.49', $p2->json('data.credit_note.total_amount'));
        $this->assertSame('34.50', $p3->json('data.credit_note.total_amount'));

        // Agotada la cantidad devolvible: sumas EXACTAS a las cifras congeladas, sin exceder ninguna.
        $sumNet = (string) DB::table('sales_returns')->where('invoice_id', $invoiceId)->sum('total_returned');
        $sumTax = (string) DB::table('credit_notes')->where('invoice_id', $invoiceId)->sum('tax_amount');
        $sumTotal = (string) DB::table('credit_notes')->where('invoice_id', $invoiceId)->sum('total_amount');
        $this->assertSame('89.99', number_format((float) $sumNet, 2, '.', ''));
        $this->assertSame('13.50', number_format((float) $sumTax, 2, '.', ''));
        $this->assertSame('103.49', number_format((float) $sumTotal, 2, '.', '')); // 89.99 + 13.50
        $this->assertSame('3.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->returned_quantity);
    }

    public function test_resarcimiento_mixto_reduccion_cxc_y_saldo_a_favor(): void
    {
        $t = $this->seedTenant('a');
        // Crédito por 100, parcialmente pagado 40 ⇒ saldo pendiente 60 (< NC de 100).
        $product = $this->makeProduct($t, '100.00', ProductType::Simple); // exento
        $this->stockFor($t, $product, '100.000');
        $saleId = $this->openSale($t);
        $siId   = $this->addItem($t, $saleId, $product, '1.000');
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '1.000']])->assertCreated();

        $ar = $this->arFor($invoiceId);
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '40.00', 'payment_method' => 'transferencia',
        ])->assertCreated();
        $this->assertSame('60.00', (string) $ar->refresh()->balance);

        // Devolución total ⇒ NC 100: 60 reducen CxC (saldo pendiente exacto) + 40 (ya pagado) saldo a favor.
        $resp = $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated();

        $resp->assertJsonPath('data.credit_note.resolution_type', 'mixto')
            ->assertJsonPath('data.credit_note.total_amount', '100.00');

        // Desglose trazable: dos vías, cada una con su monto; suman exactamente el total de la NC.
        $resolutions = collect($resp->json('data.credit_note.resolutions'))->keyBy('resolution_type');
        $this->assertSame('60.00', $resolutions['reduccion_cxc']['amount']);
        $this->assertSame('40.00', $resolutions['nota_credito_saldo']['amount']);
        $this->assertSame('100.00', bcadd($resolutions['reduccion_cxc']['amount'], $resolutions['nota_credito_saldo']['amount'], 2));

        // CxC reducida por el saldo pendiente; abono conservado.
        $ar->refresh();
        $this->assertSame('0.00', (string) $ar->balance);
        $this->assertSame('40.00', (string) $ar->paid_amount);
        $this->assertSame(1, DB::table('receivable_payments')->where('accounts_receivable_id', $ar->id)->count());

        // El excedente pagado (40) queda como saldo a favor del cliente.
        $this->asUser($t->admin)->getJson("/api/v1/customers/{$t->customer->id}/credit-balance")
            ->assertOk()->assertJsonPath('data.available_credit_balance', '40.00');
    }

    public function test_resarcimiento_mixto_reduccion_cxc_y_reembolso_efectivo(): void
    {
        $t = $this->seedTenant('a');
        $product = $this->makeProduct($t, '100.00', ProductType::Simple); // exento
        $this->stockFor($t, $product, '100.000');
        $saleId = $this->openSale($t);
        $siId   = $this->addItem($t, $saleId, $product, '1.000');
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);
        $this->dispatch($t, $invoiceId, [['sale_item_id' => $siId, 'quantity' => '1.000']])->assertCreated();

        $ar = $this->arFor($invoiceId);
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '40.00', 'payment_method' => 'transferencia',
        ])->assertCreated();

        // Devolución total con caja + ROL-01: 60 reducen CxC, SOLO el excedente pagado (40) se reembolsa.
        $resp = $this->returnRequest($t, [
            'invoice_id'      => $invoiceId,
            'cash_session_id' => $t->session->id,
            'lines'           => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'insatisfaccion']],
        ], $t->owner)->assertCreated();

        $resp->assertJsonPath('data.credit_note.resolution_type', 'mixto');
        $resolutions = collect($resp->json('data.credit_note.resolutions'))->keyBy('resolution_type');
        $this->assertSame('60.00', $resolutions['reduccion_cxc']['amount']);
        $this->assertSame('40.00', $resolutions['reembolso_efectivo']['amount']);

        // El egreso de caja es EXACTAMENTE el excedente pagado (40), no el total de la NC (100).
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'egreso_autorizado', 'amount' => '40.00', 'authorized_by' => $t->owner->id,
        ]);
        $this->assertSame('0.00', (string) $ar->refresh()->balance);
    }

    // =======================================================================
    // Atomicidad, aislamiento, policies, listados
    // =======================================================================

    public function test_rollback_integral_ante_fallo_intermedio(): void
    {
        $t = $this->seedTenant('a');
        // Dos líneas despachadas; la 2ª sobre-devuelve ⇒ revierte todo.
        $p1 = $this->makeProduct($t, '10.00', ProductType::Simple);
        $this->stockFor($t, $p1, '100.000');
        $p2 = $this->makeProduct($t, '10.00', ProductType::Simple);
        $this->stockFor($t, $p2, '100.000');

        $saleId = $this->openSale($t);
        $si1 = $this->addItem($t, $saleId, $p1, '5.000');
        $si2 = $this->addItem($t, $saleId, $p2, '5.000');
        $this->confirm($t, $saleId);
        $invoiceId = $this->invoiceCredito($t, $saleId);
        $this->dispatch($t, $invoiceId, [
            ['sale_item_id' => $si1, 'quantity' => '5.000'],
            ['sale_item_id' => $si2, 'quantity' => '5.000'],
        ])->assertCreated();

        $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [
                ['sale_item_id' => $si1, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion'],
                ['sale_item_id' => $si2, 'quantity' => '6.000', 'reason_code' => 'insatisfaccion'], // excede
            ],
        ])->assertStatus(422)->assertJsonPath('code', 'RETURN_QUANTITY');

        // NADA persistido: ni devolución, ni NC, ni reingreso, ni returned_quantity en la 1ª línea.
        $this->assertDatabaseCount('sales_returns', 0);
        $this->assertDatabaseCount('sales_return_items', 0);
        $this->assertDatabaseCount('credit_notes', 0);
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'entrada']);
        $this->assertSame('0.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($si1)->returned_quantity);
        $this->assertSame('95.000', (string) $this->stock($t, $p1)->quantity);
    }

    public function test_aislamiento_entre_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        [$invoiceId, $siId] = $this->billedDispatched($a, '5.000', credit: true);
        $returnId = $this->returnRequest($a, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated()->json('data.id');
        $creditNoteId = CreditNote::withoutGlobalScopes()->where('sales_return_id', $returnId)->firstOrFail()->id;

        // El negocio B no ve la devolución, la NC ni el saldo del cliente de A.
        $this->asUser($b->admin)->getJson("/api/v1/sales-returns/{$returnId}")->assertNotFound();
        $this->asUser($b->admin)->getJson("/api/v1/credit-notes/{$creditNoteId}")->assertNotFound();
        $this->asUser($b->admin)->getJson("/api/v1/customers/{$a->customer->id}/credit-balance")->assertNotFound();
        // Ni puede devolver contra una factura ajena.
        $this->returnRequest($b, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'otro']],
        ], $b->operator)->assertStatus(422); // invoice_id no existe en el tenant B
    }

    public function test_policies_y_roles(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true);
        $returnId = $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated()->json('data.id');

        // ROL-03 registra (ok) pero NO lista (viewAny = ROL-02+).
        $this->asUser($t->operator)->getJson('/api/v1/sales-returns')->assertStatus(403);
        $this->asUser($t->operator)->getJson("/api/v1/sales-returns/{$returnId}")->assertStatus(403);
        $this->asUser($t->operator)->getJson('/api/v1/credit-notes')->assertStatus(403);

        // ROL-02 consulta.
        $this->asUser($t->admin)->getJson('/api/v1/sales-returns')->assertOk()->assertJsonPath('meta.total', 1);
        $this->asUser($t->admin)->getJson("/api/v1/sales-returns/{$returnId}")->assertOk();
    }

    public function test_listados_filtros_orden_y_paginacion(): void
    {
        $t = $this->seedTenant('a');
        [$invA, $siA] = $this->billedDispatched($t, '5.000', credit: true);
        [$invB, $siB] = $this->billedDispatched($t, '5.000', credit: true);
        $this->returnRequest($t, ['invoice_id' => $invA, 'lines' => [['sale_item_id' => $siA, 'quantity' => '2.000', 'reason_code' => 'otro']]])->assertCreated();
        $this->returnRequest($t, ['invoice_id' => $invB, 'lines' => [['sale_item_id' => $siB, 'quantity' => '2.000', 'reason_code' => 'otro']]])->assertCreated();

        // Filtro por factura.
        $this->asUser($t->admin)->getJson("/api/v1/sales-returns?invoice_id={$invA}")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.invoice_id', $invA);

        // Orden arbitrario rechazado (allowlist).
        $this->asUser($t->admin)->getJson('/api/v1/sales-returns?sort=notes')
            ->assertStatus(422)->assertJsonValidationErrors(['sort']);

        // Paginación.
        $this->asUser($t->admin)->getJson('/api/v1/sales-returns?per_page=1')
            ->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonCount(1, 'data');

        // Credit-notes: listado + filtro por tipo.
        $this->asUser($t->admin)->getJson('/api/v1/credit-notes?resolution_type=reduccion_cxc')
            ->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_show_e_items_sin_datos_ajenos(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId, $product] = $this->billedDispatched($t, '5.000', credit: true);
        $returnId = $this->returnRequest($t, [
            'invoice_id' => $invoiceId,
            'lines'      => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ])->assertCreated()->json('data.id');

        $this->asUser($t->admin)->getJson("/api/v1/sales-returns/{$returnId}")
            ->assertOk()
            ->assertJsonPath('data.id', $returnId)
            ->assertJsonPath('data.credit_note.resolution_type', 'reduccion_cxc');

        $this->asUser($t->admin)->getJson("/api/v1/sales-returns/{$returnId}/items")
            ->assertOk()
            ->assertJsonPath('data.0.sale_item_id', $siId)
            ->assertJsonPath('data.0.quantity', '2.000')
            ->assertJsonPath('data.0.product.id', $product->id);
    }

    public function test_serializacion_impide_doble_devolucion(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000', credit: true);

        // Devolución total; una segunda sobre el mismo saldo ya agotado se rechaza (bloqueo de fila).
        $this->returnRequest($t, ['invoice_id' => $invoiceId, 'lines' => [['sale_item_id' => $siId, 'quantity' => '5.000', 'reason_code' => 'otro']]])->assertCreated();
        $this->returnRequest($t, ['invoice_id' => $invoiceId, 'lines' => [['sale_item_id' => $siId, 'quantity' => '1.000', 'reason_code' => 'otro']]])
            ->assertStatus(422)->assertJsonPath('code', 'RETURN_QUANTITY');

        $this->assertSame('5.000', (string) SaleItem::withoutGlobalScopes()->findOrFail($siId)->returned_quantity);
    }

    // =======================================================================
    // Backfill de notas de crédito históricas (migración 000002)
    // =======================================================================

    public function test_backfill_conserva_notas_historicas_es_idempotente_y_no_inventa(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceId, $siId] = $this->billedDispatched($t, '5.000'); // contado pagado 50

        // NC de reembolso en efectivo (cash_session_id NO nulo) para probar la copia exacta.
        $this->returnRequest($t, [
            'invoice_id'      => $invoiceId,
            'cash_session_id' => $t->session->id,
            'lines'           => [['sale_item_id' => $siId, 'quantity' => '2.000', 'reason_code' => 'insatisfaccion']],
        ], $t->owner)->assertCreated();

        $nc = CreditNote::withoutGlobalScopes()->where('invoice_id', $invoiceId)->firstOrFail();
        $this->assertSame('reembolso_efectivo', $nc->resolution_type->value);
        $this->assertNotNull($nc->cash_session_id);

        // Simula una NC HISTÓRICA (previa a MOD-10 v2.1): sin filas de desglose.
        DB::table('credit_note_resolutions')->where('credit_note_id', $nc->id)->delete();
        $this->assertSame(0, DB::table('credit_note_resolutions')->where('credit_note_id', $nc->id)->count());

        // Ejecuta el BACKFILL REAL de la migración (instancia única; up() es idempotente).
        $migration = require database_path('migrations/2026_09_25_000002_create_credit_note_resolutions_table.php');
        $migration->up();

        // (a) Conservación EXACTA: exactamente una resolución que copia los datos de la NC.
        $rows = DB::table('credit_note_resolutions')->where('credit_note_id', $nc->id)->get();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame((int) $nc->business_id, (int) $row->business_id);
        $this->assertSame((int) $nc->cash_session_id, (int) $row->cash_session_id);
        $this->assertSame('reembolso_efectivo', $row->resolution_type);
        $this->assertSame(
            number_format((float) $nc->total_amount, 2, '.', ''),
            number_format((float) $row->amount, 2, '.', ''),
            'El amount histórico debe coincidir exactamente con credit_notes.total_amount.'
        );

        // Ninguna NC no-mixta queda sin resolución.
        $this->assertSame(0, $this->notasNoMixtasSinResolucion());

        // (b) Idempotencia: reevaluar el backfill no duplica filas.
        $migration->up();
        $this->assertSame(1, DB::table('credit_note_resolutions')->where('credit_note_id', $nc->id)->count());

        // (c) Base sin notas: al vaciar las NCs, el backfill no inserta ninguna fila.
        DB::table('credit_note_resolutions')->delete();
        DB::table('credit_notes')->delete();
        $migration->up();
        $this->assertSame(0, DB::table('credit_note_resolutions')->count());
    }

    private function notasNoMixtasSinResolucion(): int
    {
        return DB::table('credit_notes as cn')
            ->where('cn.resolution_type', '<>', 'mixto')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('credit_note_resolutions as r')
                ->whereColumn('r.credit_note_id', 'cn.id'))
            ->count();
    }

    // =======================================================================
    // Integridad del motor MySQL (information_schema)
    // =======================================================================

    public function test_integridad_de_esquema_mysql(): void
    {
        $db = DB::getDatabaseName();

        // UNIQUE(sales_return_id) — una NC por devolución.
        $uniqReturn = DB::selectOne(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$db, 'credit_notes', 'uniq_credit_note_return'],
        );
        $this->assertNotNull($uniqReturn);
        $this->assertSame(0, (int) $uniqReturn->NON_UNIQUE);

        // UNIQUE(business_id, folio).
        $uniqFolio = DB::selectOne(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'credit_notes', 'uniq_credit_note_folio', 'folio'],
        );
        $this->assertNotNull($uniqFolio);
        $this->assertSame(0, (int) $uniqFolio->NON_UNIQUE);

        // CHECKs.
        foreach ([
            ['credit_notes', 'chk_credit_note_total'],
            ['sales_returns', 'chk_return_total'],
            ['sales_return_items', 'chk_return_item_quantity'],
            ['sale_items', 'chk_sale_item_return_not_exceed'],
            ['sale_items', 'chk_sale_item_returned_non_negative'],
        ] as [$table, $check]) {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ? AND CONSTRAINT_NAME = ?',
                [$db, $table, 'CHECK', $check],
            );
            $this->assertSame(1, (int) $row->c, "Falta el CHECK {$check} en {$table}.");
        }

        // FK credit_notes.sales_return_id → sales_returns (RESTRICT) y cn.invoice_id RESTRICT.
        $this->assertSame('RESTRICT', $this->deleteRule($db, 'credit_notes', 'sales_return_id'));
        $this->assertSame('RESTRICT', $this->deleteRule($db, 'credit_notes', 'invoice_id'));
        // FK sales_return_items.sales_return_id → sales_returns (CASCADE).
        $this->assertSame('CASCADE', $this->deleteRule($db, 'sales_return_items', 'sales_return_id'));

        // Desglose normalizado del resarcimiento (MOD-10 microcierre).
        $cnrAmount = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ? AND CONSTRAINT_NAME = ?',
            [$db, 'credit_note_resolutions', 'CHECK', 'chk_cnr_amount_positive'],
        );
        $this->assertSame(1, (int) $cnrAmount->c, 'Falta el CHECK chk_cnr_amount_positive.');
        $this->assertSame('CASCADE', $this->deleteRule($db, 'credit_note_resolutions', 'credit_note_id'));
        $this->assertSame('RESTRICT', $this->deleteRule($db, 'credit_note_resolutions', 'cash_session_id'));

        // La cabecera credit_notes.resolution_type admite 'mixto'.
        $header = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'credit_notes', 'resolution_type'],
        );
        $this->assertStringContainsString("'mixto'", (string) $header->COLUMN_TYPE);
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
