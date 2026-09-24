<?php

declare(strict_types=1);

namespace Tests\Feature\Mod07;

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
use App\Models\ProductRecipe;
use App\Models\StockLevel;
use App\Models\TaxRule;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-07 - Ventas, Facturación y subsistema fiscal (HTTP e2e contra MySQL).
 *
 * Verifica la fiscalidad normalizada: clases fiscales, resolución de tasas por
 * ámbito, fotografía fiscal congelada por línea, agregación redondeada, aislamiento
 * multitenant, cálculo exclusivamente del servidor, inmutabilidad y anulación.
 */
final class SalesFiscalHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        // Recarga desde MySQL para el guard web.
        $authenticatedUser = User::query()->whereKey($u->getKey())->firstOrFail();

        // instanceof estrecha el tipo al contrato exacto que exige actingAs()
        // (Illuminate\Contracts\Auth\Authenticatable), en vez de confiar en @var.
        if (! $authenticatedUser instanceof AuthenticatableContract) {
            throw new \RuntimeException('El usuario recargado no implementa Authenticatable.');
        }

        return $this->actingAs($authenticatedUser, 'web');
    }

    /**
     * Negocio sembrado con el observer (cliente genérico + regla fiscal estándar =
     * tax_rate). Dos sucursales con bodega por defecto, catálogo base, cliente real y
     * una sesión de caja abierta para los cobros en efectivo.
     */
    private function seedTenant(string $slug, ?string $taxRate = '0.1500'): object
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
            'tax_rate' => $taxRate,
            'timezone' => 'America/Managua',
        ]);

        $owner    = $this->makeUser($business, RoleName::Owner);
        $admin    = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator);

        $branch  = $this->makeBranch($business, 'S1 '.$slug);
        $branch2 = $this->makeBranch($business, 'S2 '.$slug);
        $warehouse  = $this->makeWarehouse($business, $branch, 'B1 '.$slug);
        $this->makeWarehouse($business, $branch2, 'B2 '.$slug);

        $category = new Category(['name' => 'Cat '.$slug]);
        $category->business_id = $business->id;
        $category->save();

        $unit = new UnitOfMeasure(['name' => 'Unidad', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $customer = new Customer([
            'name'          => 'Cliente '.$slug,
            'document_type' => 'cedula',
            'document_number' => 'DOC-'.(++self::$seq),
            'credit_limit'  => '100000.00',
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
            'branch', 'branch2', 'warehouse', 'category', 'unit', 'customer', 'session',
        );
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

    private function makeProduct(object $t, TaxClass $class, string $price = '100.00', ProductType $type = ProductType::Service): Product
    {
        $product = new Product([
            'category_id'      => $t->category->id,
            'unit_id'          => $t->unit->id,
            'sku'              => 'SKU-'.(++self::$seq),
            'name'             => 'Producto '.self::$seq,
            'type'             => $type,
            'sale_price'       => $price,
            'cost'             => '10.00',
            'tracks_inventory' => $type !== ProductType::Service,
            'tax_class'        => $class->value,
            'is_active'        => true,
        ]);
        $product->business_id = $t->business->id;
        $product->save();

        return $product;
    }

    private function makeTaxRule(object $t, TaxClass $class, string $rate, ?Branch $branch = null): TaxRule
    {
        $rule = new TaxRule();
        $rule->forceFill([
            'business_id' => $t->business->id,
            'tax_class'   => $class->value,
            'branch_id'   => $branch?->id,
            'rate'        => $rate,
            'is_active'   => true,
        ])->save();

        return $rule;
    }

    private function stockFor(object $t, Product $product, string $quantity): void
    {
        $stock = new StockLevel();
        $stock->forceFill([
            'business_id'       => $t->business->id,
            'product_id'        => $product->id,
            'warehouse_id'      => $t->warehouse->id,
            'quantity'          => $quantity,
            'reserved_quantity' => '0.000',
            'average_cost'      => '10.0000',
        ])->save();
    }

    /** Abre venta → agrega una línea → confirma. Devuelve [saleId, itemResponse]. */
    private function confirmedSale(object $t, Product $product, string $qty = '1.000', ?Branch $branch = null, string $discount = '0.00'): array
    {
        $branch ??= $t->branch;

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id'   => $branch->id,
            'customer_id' => $t->customer->id,
        ])->assertCreated()->json('data.id');

        $item = $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id'      => $product->id,
            'quantity'        => $qty,
            'discount_amount' => $discount,
        ]);

        if ($item->status() === 201) {
            $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();
        }

        return [$saleId, $item];
    }

    private function invoiceCash(object $t, int $saleId, string $amount): \Illuminate\Testing\TestResponse
    {
        return $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'payments'        => [['method' => 'efectivo', 'amount' => $amount]],
        ]);
    }

    // =======================================================================
    // Clases fiscales y resolución de tasas
    // =======================================================================

    public function test_producto_tasa_general_congela_impuesto_y_factura_agrega(): void
    {
        $t = $this->seedTenant('a', '0.1500'); // observer siembra standard general = 0.15
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');

        [$saleId, $item] = $this->confirmedSale($t, $product);

        $item->assertCreated()
            ->assertJsonPath('data.tax_class', 'standard')
            ->assertJsonPath('data.fiscal_condition', 'gravado')
            ->assertJsonPath('data.tax_rate', '0.150000')
            ->assertJsonPath('data.taxable_base', '100.00')
            ->assertJsonPath('data.tax_amount', '15.00')
            ->assertJsonPath('data.is_taxable', true);

        $this->invoiceCash($t, $saleId, '115.00')
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.tax_amount', '15.00')
            ->assertJsonPath('data.total', '115.00');
    }

    public function test_producto_tasa_reducida_usa_regla_configurada(): void
    {
        $t = $this->seedTenant('a');
        $this->makeTaxRule($t, TaxClass::Reduced, '0.070000');   // reducida general 7 %
        $product = $this->makeProduct($t, TaxClass::Reduced, '200.00');

        [, $item] = $this->confirmedSale($t, $product);

        $item->assertCreated()
            ->assertJsonPath('data.tax_class', 'reduced')
            ->assertJsonPath('data.tax_rate', '0.070000')
            ->assertJsonPath('data.tax_amount', '14.00');
    }

    public function test_producto_exento_no_grava_y_base_cero(): void
    {
        $t = $this->seedTenant('a');
        $product = $this->makeProduct($t, TaxClass::Exempt, '100.00');

        [$saleId, $item] = $this->confirmedSale($t, $product);

        $item->assertCreated()
            ->assertJsonPath('data.fiscal_condition', 'exento')
            ->assertJsonPath('data.tax_rate', '0.000000')
            ->assertJsonPath('data.taxable_base', '0.00')
            ->assertJsonPath('data.tax_amount', '0.00')
            ->assertJsonPath('data.is_taxable', false);

        $this->invoiceCash($t, $saleId, '100.00')
            ->assertCreated()
            ->assertJsonPath('data.tax_amount', '0.00')
            ->assertJsonPath('data.total', '100.00');
    }

    public function test_producto_tasa_cero_diferenciado_de_exento(): void
    {
        $t = $this->seedTenant('a');
        $product = $this->makeProduct($t, TaxClass::ZeroRated, '100.00');

        [, $item] = $this->confirmedSale($t, $product);

        // Tasa cero: gravado a 0 %, la base SÍ integra el gravamen (a diferencia del exento).
        $item->assertCreated()
            ->assertJsonPath('data.fiscal_condition', 'tasa_cero')
            ->assertJsonPath('data.tax_rate', '0.000000')
            ->assertJsonPath('data.taxable_base', '100.00')
            ->assertJsonPath('data.tax_amount', '0.00')
            ->assertJsonPath('data.is_taxable', true);
    }

    public function test_factura_con_lineas_de_tasas_distintas_suma_por_linea(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $this->makeTaxRule($t, TaxClass::Reduced, '0.070000');
        $standard = $this->makeProduct($t, TaxClass::Standard, '100.00');
        $reduced  = $this->makeProduct($t, TaxClass::Reduced, '100.00');
        $exempt   = $this->makeProduct($t, TaxClass::Exempt, '100.00');

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id' => $t->branch->id, 'customer_id' => $t->customer->id,
        ])->json('data.id');

        foreach ([$standard, $reduced, $exempt] as $p) {
            $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
                'product_id' => $p->id, 'quantity' => '1.000',
            ])->assertCreated();
        }
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        // IVA = 15 (standard) + 7 (reduced) + 0 (exempt) = 22. subtotal 300. total 322.
        $this->invoiceCash($t, $saleId, '322.00')
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '300.00')
            ->assertJsonPath('data.tax_amount', '22.00')
            ->assertJsonPath('data.total', '322.00');
    }

    public function test_regla_de_sucursal_tiene_prioridad_y_hay_fallback_general(): void
    {
        $t = $this->seedTenant('a');
        // Reducida: general 10 %, específica de la sucursal 1 = 5 %.
        $this->makeTaxRule($t, TaxClass::Reduced, '0.100000');
        $this->makeTaxRule($t, TaxClass::Reduced, '0.050000', $t->branch);
        $product = $this->makeProduct($t, TaxClass::Reduced, '100.00');

        // Sucursal 1 → regla específica 5 %.
        [, $itemB1] = $this->confirmedSale($t, $product, '1.000', $t->branch);
        $itemB1->assertCreated()->assertJsonPath('data.tax_rate', '0.050000')->assertJsonPath('data.tax_amount', '5.00');

        // Sucursal 2 (sin regla específica) → fallback general 10 %.
        [, $itemB2] = $this->confirmedSale($t, $product, '1.000', $t->branch2);
        $itemB2->assertCreated()->assertJsonPath('data.tax_rate', '0.100000')->assertJsonPath('data.tax_amount', '10.00');
    }

    public function test_negocios_con_tasas_distintas_sin_contaminacion(): void
    {
        $a = $this->seedTenant('a', '0.1500');
        $b = $this->seedTenant('b', '0.1200');

        $pa = $this->makeProduct($a, TaxClass::Standard, '100.00');
        $pb = $this->makeProduct($b, TaxClass::Standard, '100.00');

        [, $ia] = $this->confirmedSale($a, $pa);
        [, $ib] = $this->confirmedSale($b, $pb);

        $ia->assertCreated()->assertJsonPath('data.tax_amount', '15.00');
        $ib->assertCreated()->assertJsonPath('data.tax_amount', '12.00');
    }

    public function test_rechazo_controlado_sin_regla_aplicable(): void
    {
        $t = $this->seedTenant('a');
        // Producto reducido pero SIN regla reducida configurada.
        $product = $this->makeProduct($t, TaxClass::Reduced, '100.00');

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id' => $t->branch->id, 'customer_id' => $t->customer->id,
        ])->json('data.id');

        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id' => $product->id, 'quantity' => '1.000',
        ])->assertStatus(422)->assertJsonPath('code', 'FISCAL_CONFIG_MISSING');

        // La línea NO se persistió (rollback).
        $this->assertDatabaseMissing('sale_items', ['sale_id' => $saleId]);
    }

    public function test_no_se_puede_inyectar_fiscalidad_desde_el_request(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id' => $t->branch->id, 'customer_id' => $t->customer->id,
        ])->json('data.id');

        // Se intentan inyectar tasa, impuesto y total; el servidor los ignora.
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id'   => $product->id,
            'quantity'     => '1.000',
            'tax_rate'     => '0.990000',
            'tax_amount'   => '999.00',
            'taxable_base' => '999.00',
        ])->assertCreated()
            ->assertJsonPath('data.tax_rate', '0.150000')
            ->assertJsonPath('data.tax_amount', '15.00');

        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        // total inyectado ignorado: el servidor exige el total derivado (115).
        $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'tax_amount'      => '999.00',
            'total'           => '999.00',
            'payments'        => [['method' => 'efectivo', 'amount' => '115.00']],
        ])->assertCreated()->assertJsonPath('data.tax_amount', '15.00')->assertJsonPath('data.total', '115.00');
    }

    public function test_snapshot_se_conserva_si_cambia_la_tasa_despues(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');

        [$saleId, $item] = $this->confirmedSale($t, $product);
        $item->assertCreated()->assertJsonPath('data.tax_amount', '15.00');

        // El propietario sube la tasa estándar a 20 % DESPUÉS de congelar la línea.
        $rule = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')->whereNull('branch_id')->firstOrFail();
        $this->asUser($t->owner)->putJson("/api/v1/tax-rules/{$rule->id}", ['rate' => '0.200000'])
            ->assertOk()->assertJsonPath('data.rate', '0.200000');

        // La factura usa el impuesto CONGELADO (15), no el vigente (20).
        $this->invoiceCash($t, $saleId, '115.00')
            ->assertCreated()->assertJsonPath('data.tax_amount', '15.00')->assertJsonPath('data.total', '115.00');
    }

    public function test_redondeo_determinista_en_valores_fraccionarios(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        // precio 10.10 × 0.15 = 1.515 → redondeo mitad-arriba = 1.52 (truncar daría 1.51).
        $product = $this->makeProduct($t, TaxClass::Standard, '10.10');

        [, $item] = $this->confirmedSale($t, $product);

        $item->assertCreated()
            ->assertJsonPath('data.taxable_base', '10.10')
            ->assertJsonPath('data.tax_amount', '1.52');
    }

    // =======================================================================
    // Pagos, atomicidad, inmutabilidad, anulación, reserva
    // =======================================================================

    public function test_contado_exige_pago_exacto_del_total(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [$saleId] = $this->confirmedSale($t, $product);

        // Pago incompleto → 422 INCOMPLETE_PAYMENT, sin factura ni folio consumido.
        $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'payments'        => [['method' => 'efectivo', 'amount' => '100.00']],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('invoices', ['branch_id' => $t->branch->id]);
        // La venta sigue confirmada (no facturada): no hubo persistencia.
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'confirmada']);
    }

    public function test_pago_mixto_refleja_dual_solo_efectivo_en_caja(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [$saleId] = $this->confirmedSale($t, $product); // total 115

        $invoice = $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'payments'        => [
                ['method' => 'efectivo', 'amount' => '15.00'],
                ['method' => 'tarjeta', 'amount' => '100.00'],
            ],
        ])->assertCreated();

        $invoiceId = $invoice->json('data.id');
        // Dos invoice_payments; un solo cash_movement de venta (efectivo).
        $this->assertDatabaseCount('invoice_payments', 2);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'venta', 'amount' => '15.00',
        ]);
        $this->assertDatabaseMissing('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'venta', 'amount' => '100.00',
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'payment_status' => 'pagada']);
    }

    public function test_factura_a_credito_genera_cxc_atomica(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [$saleId] = $this->confirmedSale($t, $product); // total 115

        $invoice = $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'     => [$saleId],
            'payment_type' => 'credito',
        ])->assertCreated()->assertJsonPath('data.payment_type', 'credito');

        $invoiceId = $invoice->json('data.id');
        $this->assertDatabaseHas('accounts_receivables', [
            'invoice_id' => $invoiceId, 'total_amount' => '115.00',
        ]);
    }

    public function test_inmutabilidad_y_anulacion_por_rol01(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [$saleId] = $this->confirmedSale($t, $product);
        $invoiceId = $this->invoiceCash($t, $saleId, '115.00')->assertCreated()->json('data.id');

        // Núcleo inmutable: PUT → 403 IMMUTABLE_INVOICE.
        $this->asUser($t->admin)->putJson("/api/v1/invoices/{$invoiceId}", ['total' => '0.00'])
            ->assertStatus(403);

        // Anular: ROL-03 no puede.
        $this->asUser($t->operator)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'x'])
            ->assertStatus(403);

        // ROL-01 anula con motivo.
        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'Error de emisión'])
            ->assertOk()->assertJsonPath('data.status', 'anulada')->assertJsonPath('data.voided_by', $t->owner->id);
    }

    public function test_reserva_simple_y_liberacion_en_anulacion(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00', ProductType::Simple);
        $this->stockFor($t, $product, '10.000');

        [$saleId] = $this->confirmedSale($t, $product, '3.000');
        $invoiceId = $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'payments'        => [['method' => 'efectivo', 'amount' => '345.00']],
        ])->assertCreated()->json('data.id');

        // Reserva sin descontar quantity.
        $stock = StockLevel::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('3.000', (string) $stock->reserved_quantity);
        $this->assertSame('10.000', (string) $stock->quantity);

        // Anulación libera la reserva.
        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'Anulada'])->assertOk();
        $this->assertSame('0.000', (string) StockLevel::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail()->reserved_quantity);
    }

    public function test_reserva_compuesta_por_recipe_snapshot(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $ingredient = $this->makeProduct($t, TaxClass::Standard, '0.00', ProductType::Simple);
        $this->stockFor($t, $ingredient, '20.000');

        $compound = $this->makeProduct($t, TaxClass::Standard, '50.00', ProductType::Compound);
        $recipe = new ProductRecipe();
        $recipe->forceFill([
            'business_id'   => $t->business->id,
            'compound_id'   => $compound->id,
            'ingredient_id' => $ingredient->id,
            'quantity'      => '2.000',
            'unit_id'       => $t->unit->id,
        ])->save();

        [$saleId] = $this->confirmedSale($t, $compound, '3.000'); // 3 compuestos × 2 insumos = 6 reservados
        $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids'        => [$saleId],
            'payment_type'    => 'contado',
            'cash_session_id' => $t->session->id,
            'payments'        => [['method' => 'efectivo', 'amount' => '172.50']],
        ])->assertCreated();

        $stock = StockLevel::withoutGlobalScopes()->where('product_id', $ingredient->id)->firstOrFail();
        $this->assertSame('6.000', (string) $stock->reserved_quantity);
    }

    // =======================================================================
    // Administración fiscal (ROL-01) y aislamiento
    // =======================================================================

    public function test_admin_fiscal_solo_rol01_y_conflicto_de_ambito(): void
    {
        $t = $this->seedTenant('a', '0.1500');

        // ROL-03/ROL-02 no pueden administrar reglas fiscales.
        $this->asUser($t->operator)->getJson('/api/v1/tax-rules')->assertStatus(403);
        $this->asUser($t->admin)->postJson('/api/v1/tax-rules', [
            'tax_class' => 'reduced', 'rate' => '0.070000',
        ])->assertStatus(403);

        // ROL-01 crea una regla reducida general.
        $this->asUser($t->owner)->postJson('/api/v1/tax-rules', [
            'tax_class' => 'reduced', 'rate' => '0.070000',
        ])->assertCreated()->assertJsonPath('data.tax_class', 'reduced')->assertJsonPath('data.scope', 'business');

        // Segunda regla estándar general activa → conflicto (ya la sembró el observer).
        $this->asUser($t->owner)->postJson('/api/v1/tax-rules', [
            'tax_class' => 'standard', 'rate' => '0.180000',
        ])->assertStatus(422)->assertJsonValidationErrors(['tax_class']);

        // Desactivar la estándar general (baja lógica, no borrado).
        $rule = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')->whereNull('branch_id')->firstOrFail();
        $this->asUser($t->owner)->deleteJson("/api/v1/tax-rules/{$rule->id}")
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('tax_rules', ['id' => $rule->id, 'is_active' => 0]);
    }

    public function test_aislamiento_de_facturas_entre_negocios(): void
    {
        $a = $this->seedTenant('a', '0.1500');
        $b = $this->seedTenant('b', '0.1500');
        $product = $this->makeProduct($a, TaxClass::Standard, '100.00');
        [$saleId] = $this->confirmedSale($a, $product);
        $invoiceId = $this->invoiceCash($a, $saleId, '115.00')->assertCreated()->json('data.id');

        // El negocio B no ve la factura de A (BusinessScope → 404).
        $this->asUser($b->operator)->getJson("/api/v1/invoices/{$invoiceId}")->assertNotFound();
        // Ni las reglas fiscales de A.
        $this->asUser($b->owner)->getJson('/api/v1/tax-rules')
            ->assertOk()->assertJsonPath('meta.total', 1); // solo su propia estándar
    }

    // =======================================================================
    // Microcierre: alias is_taxable, versionado de reglas, business.tax_rate
    // =======================================================================

    private function productPayload(object $t, array $overrides): array
    {
        return array_merge([
            'sku'         => 'SKU-'.(++self::$seq),
            'name'        => 'Producto '.self::$seq,
            'type'        => 'service',
            'category_id' => $t->category->id,
            'unit_id'     => $t->unit->id,
            'sale_price'  => '10.00',
            'cost'        => '5.00',
        ], $overrides);
    }

    public function test_alias_is_taxable_en_alta_de_producto(): void
    {
        $t = $this->seedTenant('a');

        // is_taxable=true sin tax_class → standard (gravado).
        $this->asUser($t->admin)->postJson('/api/v1/products', $this->productPayload($t, ['is_taxable' => true]))
            ->assertCreated()->assertJsonPath('data.tax_class', 'standard')->assertJsonPath('data.is_taxable', true);

        // is_taxable=false sin tax_class → exempt.
        $this->asUser($t->admin)->postJson('/api/v1/products', $this->productPayload($t, ['is_taxable' => false]))
            ->assertCreated()->assertJsonPath('data.tax_class', 'exempt')->assertJsonPath('data.is_taxable', false);
    }

    public function test_alias_is_taxable_en_update_de_producto(): void
    {
        $t = $this->seedTenant('a');
        $product = $this->makeProduct($t, TaxClass::Standard, '10.00');

        // is_taxable=false (alias) traduce la clase a exempt.
        $this->asUser($t->admin)->putJson("/api/v1/products/{$product->id}", ['is_taxable' => false])
            ->assertOk()->assertJsonPath('data.tax_class', 'exempt')->assertJsonPath('data.is_taxable', false);
    }

    public function test_is_taxable_contradice_tax_class_devuelve_422(): void
    {
        $t = $this->seedTenant('a');

        // Alta: tax_class=standard + is_taxable=false → contradicción.
        $this->asUser($t->admin)->postJson('/api/v1/products', $this->productPayload($t, [
            'tax_class' => 'standard', 'is_taxable' => false,
        ]))->assertStatus(422)->assertJsonValidationErrors(['is_taxable']);

        // Update: tax_class=exempt + is_taxable=true → contradicción.
        $product = $this->makeProduct($t, TaxClass::Standard, '10.00');
        $this->asUser($t->admin)->putJson("/api/v1/products/{$product->id}", [
            'tax_class' => 'exempt', 'is_taxable' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['is_taxable']);
    }

    public function test_versionado_de_tasa_preserva_regla_anterior(): void
    {
        $t = $this->seedTenant('a', '0.1500');
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [, $item] = $this->confirmedSale($t, $product);
        $itemId    = $item->json('data.id');
        $oldRuleId = $item->json('data.tax_rule_id');

        $rule = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')
            ->whereNull('branch_id')->where('is_active', true)->firstOrFail();
        $this->assertSame($oldRuleId, $rule->id, 'La línea congeló la regla activa vigente.');

        // PUT tasa 0.20 → NUEVA versión activa; la anterior se conserva inactiva.
        $resp = $this->asUser($t->owner)->putJson("/api/v1/tax-rules/{$rule->id}", ['rate' => '0.200000'])
            ->assertOk()->assertJsonPath('data.rate', '0.200000');
        $newRuleId = $resp->json('data.id');
        $this->assertNotSame($oldRuleId, $newRuleId);

        $this->assertDatabaseHas('tax_rules', ['id' => $oldRuleId, 'is_active' => 0, 'rate' => '0.150000']);
        $this->assertDatabaseHas('tax_rules', ['id' => $newRuleId, 'is_active' => 1, 'rate' => '0.200000']);

        // La línea histórica sigue apuntando a la versión ORIGINAL con su tasa congelada.
        $this->assertDatabaseHas('sale_items', ['id' => $itemId, 'tax_rule_id' => $oldRuleId, 'tax_rate' => '0.150000']);
    }

    public function test_business_tax_rate_se_traduce_a_la_regla_estandar(): void
    {
        $t = $this->seedTenant('a', '0.1500');

        // Editar la tasa del negocio DEBE afectar la facturación (traducción a la regla).
        $this->asUser($t->owner)->putJson('/api/v1/business', ['tax_rate' => '0.2000'])->assertOk();

        // Una venta posterior usa 0.20 (la regla estándar se versionó).
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [, $item] = $this->confirmedSale($t, $product);
        $item->assertCreated()->assertJsonPath('data.tax_rate', '0.200000')->assertJsonPath('data.tax_amount', '20.00');

        // business.tax_rate es alias derivado de la regla vigente (no una 2ª fuente).
        $this->asUser($t->owner)->getJson('/api/v1/business')->assertOk()->assertJsonPath('data.tax_rate', '0.200000');

        // No hay dos reglas estándar generales activas: la anterior quedó versionada.
        $this->assertSame(1, TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')
            ->whereNull('branch_id')->where('is_active', true)->count());
    }

    public function test_candado_motor_bloquea_reglas_activas_duplicadas(): void
    {
        $t = $this->seedTenant('a'); // observer sembró la estándar general activa

        // Dos reglas GENERALES activas (branch_id NULL) deben colisionar: lo garantiza la
        // columna generada active_scope_lock (un UNIQUE convencional con NULL no bastaría).
        try {
            (new TaxRule())->forceFill([
                'business_id' => $t->business->id, 'tax_class' => 'standard',
                'branch_id' => null, 'rate' => '0.900000', 'is_active' => true,
            ])->save();
            $this->fail('El motor debía rechazar dos reglas generales activas.');
        } catch (QueryException $e) {
            $this->assertSame(1062, (int) ($e->errorInfo[1] ?? 0));
        }

        // Colisión también por ámbito de sucursal; distintas sucursales NO colisionan.
        $this->makeTaxRule($t, TaxClass::Reduced, '0.050000', $t->branch);
        try {
            $this->makeTaxRule($t, TaxClass::Reduced, '0.060000', $t->branch);
            $this->fail('El motor debía rechazar dos reglas activas de la misma sucursal.');
        } catch (QueryException $e) {
            $this->assertSame(1062, (int) ($e->errorInfo[1] ?? 0));
        }
        $this->makeTaxRule($t, TaxClass::Reduced, '0.050000', $t->branch2); // otra sucursal: OK
        $this->assertDatabaseCount('tax_rules', 3); // standard general + 2 reducidas de sucursal
    }

    public function test_reactivacion_conflictiva_devuelve_422_no_500(): void
    {
        $t = $this->seedTenant('a');

        $ruleA = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')
            ->whereNull('branch_id')->where('is_active', true)->firstOrFail();

        // Desactivar A, crear C activa (mismo ámbito).
        $this->asUser($t->owner)->deleteJson("/api/v1/tax-rules/{$ruleA->id}")->assertOk()->assertJsonPath('data.is_active', false);
        $this->asUser($t->owner)->postJson('/api/v1/tax-rules', ['tax_class' => 'standard', 'rate' => '0.180000'])->assertCreated();

        // Reactivar A choca con C → 1062 traducido a 422 (nunca 500).
        $this->asUser($t->owner)->putJson("/api/v1/tax-rules/{$ruleA->id}", ['is_active' => true])
            ->assertStatus(422)->assertJsonValidationErrors(['tax_class']);
    }

    /**
     * Verificación SEGURA (sin DDL/migrate:fresh) de la LÓGICA de transformación que
     * aplica el backfill (migración 000004): (1) regla estándar general del negocio =
     * su tasa anterior; (2) is_taxable=true→standard, is_taxable=false→exempt; (3)
     * is_taxable ya no persiste como columna (fuente única = tax_class). El backfill de
     * datos históricos NO se prueba aquí (la base autorizada está vacía): ver consultas
     * de preflight en el informe.
     */
    public function test_semantica_de_transformacion_del_backfill(): void
    {
        // (1) El observer siembra la regla estándar general con la tasa del negocio.
        $t = $this->seedTenant('a', '0.1300');
        $rule = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')
            ->whereNull('branch_id')->where('is_active', true)->firstOrFail();
        $this->assertSame('0.130000', (string) $rule->rate);

        // (2) Mapeo is_taxable→tax_class idéntico al del backfill.
        $this->asUser($t->admin)->postJson('/api/v1/products', $this->productPayload($t, ['is_taxable' => true]))
            ->assertCreated()->assertJsonPath('data.tax_class', 'standard');
        $this->asUser($t->admin)->postJson('/api/v1/products', $this->productPayload($t, ['is_taxable' => false]))
            ->assertCreated()->assertJsonPath('data.tax_class', 'exempt');

        // (3) is_taxable NO es una segunda fuente persistida: la columna no existe.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_taxable'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('products', 'tax_class'));
    }

    // =======================================================================
    // Microcierre: fin de la presunción fiscal universal (sin 15 % por defecto)
    // =======================================================================

    public function test_negocio_nuevo_sin_tasa_no_recibe_15_ni_regla_estandar(): void
    {
        $t = $this->seedTenant('a', null); // negocio SIN tasa explícita

        // No hereda 0.1500 en silencio: la columna quedó null.
        $this->assertNull($t->business->fresh()->tax_rate);

        // NO se sembró ninguna regla estándar automática.
        $this->assertSame(0, TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')->count());

        // BusinessResource devuelve tax_rate=null (no hay regla estándar).
        $this->asUser($t->owner)->getJson('/api/v1/business')
            ->assertOk()->assertJsonPath('data.tax_rate', null);
    }

    public function test_facturacion_gravada_sin_regla_devuelve_422_y_revierte(): void
    {
        $t = $this->seedTenant('a', null);
        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id' => $t->branch->id, 'customer_id' => $t->customer->id,
        ])->json('data.id');

        // Producto gravado sin regla estándar aplicable → 422 FISCAL_CONFIG_MISSING.
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id' => $product->id, 'quantity' => '1.000',
        ])->assertStatus(422)->assertJsonPath('code', 'FISCAL_CONFIG_MISSING');

        // Rollback total: la línea no se persistió.
        $this->assertDatabaseMissing('sale_items', ['sale_id' => $saleId]);
    }

    public function test_configurar_tasa_despues_permite_facturar(): void
    {
        $t = $this->seedTenant('a', null);

        // Configuración posterior explícita: 12 % vía POST /tax-rules.
        $this->asUser($t->owner)->postJson('/api/v1/tax-rules', [
            'tax_class' => 'standard', 'rate' => '0.120000',
        ])->assertCreated();

        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [$saleId, $item] = $this->confirmedSale($t, $product);
        $item->assertCreated()
            ->assertJsonPath('data.tax_rate', '0.120000')
            ->assertJsonPath('data.tax_amount', '12.00');

        $this->invoiceCash($t, $saleId, '112.00')
            ->assertCreated()->assertJsonPath('data.tax_amount', '12.00')->assertJsonPath('data.total', '112.00');
    }

    public function test_negocio_con_tasa_explicita_conserva_aprovisionamiento(): void
    {
        // Equivalente al backfill de negocios existentes: tasa declarada → regla estándar.
        $t = $this->seedTenant('a', '0.1500');

        $rule = TaxRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('tax_class', 'standard')
            ->whereNull('branch_id')->where('is_active', true)->firstOrFail();
        $this->assertSame('0.150000', (string) $rule->rate);

        $product = $this->makeProduct($t, TaxClass::Standard, '100.00');
        [, $item] = $this->confirmedSale($t, $product);
        $item->assertCreated()->assertJsonPath('data.tax_amount', '15.00');

        // El Resource refleja la tasa vigente (derivada de la regla).
        $this->asUser($t->owner)->getJson('/api/v1/business')->assertOk()->assertJsonPath('data.tax_rate', '0.150000');
    }
}
