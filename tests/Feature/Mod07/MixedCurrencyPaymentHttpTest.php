<?php

declare(strict_types=1);

namespace Tests\Feature\Mod07;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\EquipsOperativeProfiles;
use Tests\MysqlTestCase;

/**
 * MOD-07/06 · Pagos mixtos NIO/USD contra una factura denominada en NIO. Cada leg conserva moneda, importe
 * nativo y tasa snapshot; el total se salda por la SUMA de equivalentes NIO. El efectivo (de cualquier
 * moneda) asienta su movimiento de caja con la MISMA tasa congelada. Compatibilidad: pagos NIO intactos.
 */
final class MixedCurrencyPaymentHttpTest extends MysqlTestCase
{
    use EquipsOperativeProfiles;

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
            throw new \RuntimeException('no auth');
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
            'name' => 'Negocio '.$slug, 'slug' => $slug.'-'.(++self::$seq),
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);

        $owner = $this->makeUser($business, RoleName::Owner);
        $operator = $this->makeUser($business, RoleName::Operator);
        $branch = $this->makeBranch($business, 'S1 '.$slug);
        $warehouse = $this->makeWarehouse($business, $branch, 'B1 '.$slug);
        $this->equipOperator($operator, $branch->id);

        $category = new Category(['name' => 'Cat '.$slug]);
        $category->business_id = $business->id;
        $category->save();

        $unit = new UnitOfMeasure(['name' => 'Unidad', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $customer = new Customer(['name' => 'Cliente '.$slug, 'document_type' => 'cedula', 'document_number' => 'DOC-'.(++self::$seq), 'credit_limit' => '100000.00']);
        $customer->business_id = $business->id;
        $customer->save();

        $register = new CashRegister();
        $register->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Caja '.$slug, 'is_active' => true])->save();

        $session = new CashSession();
        $session->forceFill([
            'business_id' => $business->id, 'cash_register_id' => $register->id, 'opened_by' => $operator->id,
            'status' => 'abierta', 'opening_amount' => '0.00', 'opening_amount_usd' => '0.00', 'opened_at' => now(),
        ])->saveQuietly();

        return (object) compact('business', 'owner', 'operator', 'branch', 'warehouse', 'category', 'unit', 'customer', 'session');
    }

    private function makeUser(Business $business, RoleName $role): User
    {
        $user = new User(['name' => $role->value.' '.(++self::$seq), 'email' => 'u'.self::$seq.'@test.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true]);
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
        $branch->forceFill(['business_id' => $business->id, 'name' => $name, 'address' => 'Dir', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $business, Branch $branch, string $name): Warehouse
    {
        $warehouse = new Warehouse();
        $warehouse->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => $name, 'is_default' => true, 'is_active' => true])->save();

        return $warehouse;
    }

    private function makeProduct(object $t, string $price = '100.00'): Product
    {
        $product = new Product([
            'category_id' => $t->category->id, 'unit_id' => $t->unit->id, 'sku' => 'SKU-'.(++self::$seq),
            'name' => 'Producto '.self::$seq, 'type' => ProductType::Service, 'sale_price' => $price, 'cost' => '10.00',
            'tracks_inventory' => false, 'tax_class' => TaxClass::Standard->value, 'is_active' => true,
        ]);
        $product->business_id = $t->business->id;
        $product->save();

        return $product;
    }

    private function seedRate(object $t, string $currency, string $rate): void
    {
        $row = new ExchangeRate;
        $row->forceFill([
            'business_id' => $t->business->id, 'currency' => $currency, 'rate' => $rate,
            'effective_from' => Carbon::now()->subDay(), 'created_by' => $t->owner->id,
        ])->save();
    }

    private function confirmedSale(object $t, Product $product): int
    {
        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id' => $t->branch->id, 'customer_id' => $t->customer->id,
        ])->assertCreated()->json('data.id');

        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id' => $product->id, 'quantity' => '1.000',
        ])->assertCreated();
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        return (int) $saleId;
    }

    // ---------------- Pago mixto NIO + USD ----------------

    public function test_pago_mixto_nio_y_usd_salda_por_equivalente(): void
    {
        $t = $this->seedTenant('mix');
        $this->seedRate($t, 'USD', '13.00'); // 1 USD = 13 NIO
        $product = $this->makeProduct($t, '100.00'); // total 115 (IVA 15)
        $saleId = $this->confirmedSale($t, $product);

        // 50 NIO efectivo + 5 USD efectivo (=65 NIO) = 115 NIO → salda exacto.
        $invoiceId = $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'contado', 'cash_session_id' => $t->session->id,
            'payments' => [
                ['method' => 'efectivo', 'amount' => '50.00'],
                ['method' => 'efectivo', 'amount' => '5.00', 'currency' => 'USD'],
            ],
        ])->assertCreated()->assertJsonPath('data.total', '115.00')->json('data.id');

        // Dos invoice_payments: NIO (base 50) y USD (nativo 5, tasa 13, base 65).
        $this->assertSame(2, InvoicePayment::withoutGlobalScopes()->where('invoice_id', $invoiceId)->count());
        $usdLeg = InvoicePayment::withoutGlobalScopes()->where('invoice_id', $invoiceId)->where('currency', 'USD')->firstOrFail();
        $this->assertSame('5.00', (string) $usdLeg->amount);
        $this->assertSame(0, bccomp((string) $usdLeg->exchange_rate, '13', 6));
        $this->assertSame('65.00', (string) $usdLeg->base_amount);

        // Dos movimientos de caja de venta (ambos efectivo): NIO 50 y USD 5 (base 65, misma tasa).
        $usdMov = CashMovement::withoutGlobalScopes()
            ->where('cash_session_id', $t->session->id)->where('category', 'venta')->where('currency', 'USD')->firstOrFail();
        $this->assertSame('5.00', (string) $usdMov->amount);
        $this->assertSame('65.00', (string) $usdMov->base_amount);
        $this->assertSame(0, bccomp((string) $usdMov->exchange_rate, '13', 6));
    }

    public function test_pago_usd_sin_tasa_vigente_rechaza_y_revierte(): void
    {
        $t = $this->seedTenant('mix');
        $product = $this->makeProduct($t, '100.00'); // total 115
        $saleId = $this->confirmedSale($t, $product);

        $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'contado', 'cash_session_id' => $t->session->id,
            'payments' => [['method' => 'efectivo', 'amount' => '8.84', 'currency' => 'USD']], // sin tasa configurada
        ])->assertStatus(422)->assertJsonPath('code', 'EXCHANGE_RATE_MISSING');

        // Rollback total: ni factura, ni pagos, ni movimientos.
        $this->assertDatabaseMissing('invoices', ['branch_id' => $t->branch->id]);
        $this->assertSame(0, CashMovement::withoutGlobalScopes()->where('cash_session_id', $t->session->id)->where('category', 'venta')->count());
    }

    public function test_pago_mixto_insuficiente_en_equivalente_rechaza(): void
    {
        $t = $this->seedTenant('mix');
        $this->seedRate($t, 'USD', '13.00');
        $product = $this->makeProduct($t, '100.00'); // total 115
        $saleId = $this->confirmedSale($t, $product);

        // 50 NIO + 4 USD (=52) = 102 < 115 → pago incompleto.
        $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'contado', 'cash_session_id' => $t->session->id,
            'payments' => [
                ['method' => 'efectivo', 'amount' => '50.00'],
                ['method' => 'efectivo', 'amount' => '4.00', 'currency' => 'USD'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('invoices', ['branch_id' => $t->branch->id]);
    }

    // ---------------- Vuelto (cambio) ----------------

    public function test_vuelto_misma_moneda_neto_salda_total(): void
    {
        $t = $this->seedTenant('mix');
        $product = $this->makeProduct($t, '100.00'); // total 115
        $saleId = $this->confirmedSale($t, $product);

        // Entrega 200 NIO, vuelto 85 NIO → neto 115 = total.
        $invoiceId = $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'contado', 'cash_session_id' => $t->session->id,
            'payments' => [['method' => 'efectivo', 'amount' => '200.00']],
            'change' => ['amount' => '85.00'], // NIO por defecto
        ])->assertCreated()->json('data.id');

        // Factura saldada al NETO (115), no a lo entregado (200).
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'paid_amount' => '115.00', 'payment_status' => 'pagada']);

        // Caja: ingreso venta 200 + egreso vuelto 85 ⇒ efectivo neto 115.
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'venta', 'amount' => '200.00', 'currency' => 'NIO',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'vuelto', 'type' => 'egreso', 'amount' => '85.00', 'currency' => 'NIO',
        ]);
    }

    public function test_vuelto_cruzado_usd_entregado_cambio_en_nio(): void
    {
        $t = $this->seedTenant('mix');
        $this->seedRate($t, 'USD', '13.00');
        $product = $this->makeProduct($t, '100.00'); // total 115
        $saleId = $this->confirmedSale($t, $product);

        // Entrega 10 USD (=130 NIO), vuelto 15 NIO → neto 115 = total.
        $invoiceId = $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'contado', 'cash_session_id' => $t->session->id,
            'payments' => [['method' => 'efectivo', 'amount' => '10.00', 'currency' => 'USD']],
            'change' => ['amount' => '15.00', 'currency' => 'NIO'],
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'paid_amount' => '115.00', 'payment_status' => 'pagada']);

        // Caja: +10 USD venta, −15 NIO vuelto (efectos reales por moneda).
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'venta', 'amount' => '10.00', 'currency' => 'USD',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'vuelto', 'amount' => '15.00', 'currency' => 'NIO',
        ]);
    }

    public function test_vuelto_mayor_que_efectivo_recibido_rechaza(): void
    {
        $t = $this->seedTenant('mix');
        $product = $this->makeProduct($t, '100.00'); // total 115
        $saleId = $this->confirmedSale($t, $product);

        // Entrega 120 NIO pero pretende 200 de vuelto (> efectivo recibido) → 422, rollback total.
        $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'contado', 'cash_session_id' => $t->session->id,
            'payments' => [['method' => 'efectivo', 'amount' => '120.00']],
            'change' => ['amount' => '200.00'],
        ])->assertStatus(422)->assertJsonValidationErrors(['change']);

        $this->assertDatabaseMissing('invoices', ['branch_id' => $t->branch->id]);
        $this->assertSame(0, CashMovement::withoutGlobalScopes()->where('cash_session_id', $t->session->id)->count());
    }
}
