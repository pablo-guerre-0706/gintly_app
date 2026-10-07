<?php

declare(strict_types=1);

namespace Tests\Feature\Mod10;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\AccountReceivable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
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
 * MOD-10 · Microcierre — LECTURA autorizada para preparar devoluciones.
 * GET /sales-returns/eligible-invoices y /{invoice}/items: el BODEGUERO (devoluciones.crear, SIN
 * facturas.ver) descubre facturas y líneas devolvibles de SU sucursal. Devolvible = dispatched − returned > 0.
 */
final class ReturnEligibilityHttpTest extends MysqlTestCase
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
        $branch1 = $this->makeBranch($business, 'S1');
        $branch2 = $this->makeBranch($business, 'S2');
        $wh1 = $this->makeWarehouse($business, $branch1, 'B1');
        $this->makeWarehouse($business, $branch2, 'B2');

        $owner = $this->makeUser($business, RoleName::Owner);
        $bodeguero = $this->makeUser($business, RoleName::Operator, $branch1);
        $this->assignProfile($bodeguero, $business->id, 'bodeguero');
        $cajero = $this->makeUser($business, RoleName::Operator, $branch1);
        $this->assignProfile($cajero, $business->id, 'cajero');

        $category = new Category(['name' => 'Cat '.self::$seq]);
        $category->business_id = $business->id;
        $category->save();
        $unit = new UnitOfMeasure(['name' => 'u', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();
        $product = $this->makeProduct($business, $category->id, $unit->id, ProductType::Simple);
        $service = $this->makeProduct($business, $category->id, $unit->id, ProductType::Service);
        $customer = new Customer(['name' => 'C', 'is_active' => true]);
        $customer->business_id = $business->id;
        $customer->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'branch1', 'branch2', 'wh1', 'owner', 'bodeguero', 'cajero', 'product', 'service', 'customer');
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

    private function makeProduct(Business $b, int $categoryId, int $unitId, ProductType $type): Product
    {
        $p = new Product([
            'category_id' => $categoryId, 'unit_id' => $unitId, 'sku' => 'SKU-'.(++self::$seq),
            'name' => $type->value.' '.self::$seq, 'type' => $type, 'sale_price' => '10.00', 'cost' => '4.0000',
            'tracks_inventory' => $type !== ProductType::Service, 'tax_class' => TaxClass::Exempt->value, 'is_active' => true,
        ]);
        $p->business_id = $b->id;
        $p->save();

        return $p;
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

    /**
     * Factura con UNA línea, controlando dispatched/returned/estado/tipo de pago. Devuelve [Invoice, SaleItem].
     *
     * @return array{0: Invoice, 1: SaleItem}
     */
    private function invoiceWithLine(
        object $t, Branch $branch, Product $product, string $dispatched, string $returned,
        string $status = 'emitida', string $paymentType = 'contado', bool $withCxC = false, string $qty = '5.000'
    ): array {
        $lineTotal = bcmul($qty, '10.00', 2);

        $sale = new Sale;
        $sale->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'customer_id' => $t->customer->id,
            'user_id' => $t->owner->id, 'code' => 'V-'.(++self::$seq), 'status' => 'facturada',
            'subtotal' => $lineTotal, 'opened_at' => now(), 'confirmed_at' => now(),
        ])->save();

        $item = new SaleItem;
        $item->forceFill([
            'business_id' => $t->business->id, 'sale_id' => $sale->id, 'product_id' => $product->id,
            'description' => $product->name, 'quantity' => $qty, 'unit_price' => '10.00', 'unit_cost' => '4.0000',
            'is_taxable' => false, 'discount_amount' => '0.00', 'line_total' => $lineTotal,
            'tax_class' => 'exempt', 'fiscal_condition' => 'exento', 'tax_rate' => '0.000000',
            'taxable_base' => $lineTotal, 'tax_amount' => '0.00',
            'dispatched_quantity' => $dispatched, 'returned_quantity' => $returned,
        ])->save();

        $paid = $paymentType === 'contado' ? $lineTotal : '0.00';
        $invoice = new Invoice;
        $invoice->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'customer_id' => $t->customer->id,
            'cash_session_id' => null, 'folio' => 'F-'.(++self::$seq), 'payment_type' => $paymentType,
            'payment_status' => $paymentType === 'contado' ? 'pagada' : 'pendiente', 'status' => $status,
            'subtotal' => $lineTotal, 'tax_amount' => '0.00', 'discount_amount' => '0.00', 'total' => $lineTotal,
            'paid_amount' => $paid, 'issued_at' => now(), 'issued_by' => $t->owner->id,
            // chk_invoice_void_coherence: una factura anulada exige voided_by.
            'voided_by' => $status === 'anulada' ? $t->owner->id : null,
            'voided_at' => $status === 'anulada' ? now() : null,
            'void_reason' => $status === 'anulada' ? 'prueba' : null,
        ])->saveQuietly();
        $invoice->sales()->attach($sale->id, ['business_id' => $t->business->id]);

        if ($withCxC) {
            $ar = new AccountReceivable;
            $ar->forceFill([
                'business_id' => $t->business->id, 'customer_id' => $t->customer->id, 'invoice_id' => $invoice->id,
                'total_amount' => $lineTotal, 'paid_amount' => '0.00', 'due_date' => now()->addDays(30)->toDateString(), 'status' => 'pendiente',
            ])->save();
        }

        return [$invoice, $item];
    }

    // ---------------- Autorización ----------------

    public function test_bodeguero_lista_elegibles_pero_cajero_no(): void
    {
        $t = $this->seedTenant('a');
        $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '0.000');

        $this->asUser($t->bodeguero)->getJson('/api/v1/sales-returns/eligible-invoices')->assertOk();
        $this->asUser($t->cajero)->getJson('/api/v1/sales-returns/eligible-invoices')->assertStatus(403); // sin devoluciones.crear.
    }

    public function test_bodeguero_no_accede_a_invoices_general(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->bodeguero)->getJson('/api/v1/invoices')->assertStatus(403); // no tiene facturas.ver.
    }

    // ---------------- Aislamiento + exclusiones ----------------

    public function test_lista_solo_facturas_devolvibles_de_su_sucursal(): void
    {
        $t = $this->seedTenant('a');
        [$elegible] = $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '0.000');      // S1, devolvible.
        $this->invoiceWithLine($t, $t->branch2, $t->product, '5.000', '0.000');                    // S2 → excluida.
        $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '0.000', status: 'anulada'); // anulada → excluida.
        $this->invoiceWithLine($t, $t->branch1, $t->product, '0.000', '0.000');                    // no entregada → excluida.
        $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '5.000');                    // totalmente devuelta → excluida.
        $this->invoiceWithLine($t, $t->branch1, $t->service, '0.000', '0.000');                    // servicio (no entregado) → excluida.

        $data = $this->asUser($t->bodeguero)->getJson('/api/v1/sales-returns/eligible-invoices')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($elegible->id, $data[0]['invoice_id']);
        $this->assertSame($elegible->folio, $data[0]['folio']);
    }

    public function test_items_calcula_cantidad_devolvible(): void
    {
        $t = $this->seedTenant('a');
        [$invoice, $item] = $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '2.000');

        $data = $this->asUser($t->bodeguero)->getJson("/api/v1/sales-returns/eligible-invoices/{$invoice->id}/items")
            ->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($item->id, $data[0]['sale_item_id']);
        $this->assertSame('5.000', $data[0]['delivered_quantity']);
        $this->assertSame('2.000', $data[0]['returned_quantity']);
        $this->assertSame('3.000', $data[0]['returnable_quantity']);
    }

    public function test_items_de_otra_sucursal_404(): void
    {
        $t = $this->seedTenant('a');
        [$invoiceS2] = $this->invoiceWithLine($t, $t->branch2, $t->product, '5.000', '0.000');

        $this->asUser($t->bodeguero)->getJson("/api/v1/sales-returns/eligible-invoices/{$invoiceS2->id}/items")
            ->assertStatus(404); // no revela existencia de facturas ajenas.
    }

    public function test_items_de_otro_negocio_404(): void
    {
        $t = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        [$invoiceB] = $this->invoiceWithLine($b, $b->branch1, $b->product, '5.000', '0.000');

        $this->asUser($t->bodeguero)->getJson("/api/v1/sales-returns/eligible-invoices/{$invoiceB->id}/items")
            ->assertStatus(404);
    }

    public function test_items_de_factura_anulada_404(): void
    {
        $t = $this->seedTenant('a');
        [$anulada] = $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '0.000', status: 'anulada');

        $this->asUser($t->bodeguero)->getJson("/api/v1/sales-returns/eligible-invoices/{$anulada->id}/items")
            ->assertStatus(404);
    }

    // ---------------- Integración con el alta real ----------------

    public function test_ids_descubiertos_funcionan_en_post_create(): void
    {
        $t = $this->seedTenant('a');
        // Crédito con CxC viva → la devolución reduce CxC (sin reembolso en efectivo ni ROL-01).
        [$invoice, $item] = $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '0.000', paymentType: 'credito', withCxC: true);

        // 1) Descubrir vía el endpoint autorizado.
        $items = $this->asUser($t->bodeguero)->getJson("/api/v1/sales-returns/eligible-invoices/{$invoice->id}/items")
            ->assertOk()->json('data');
        $saleItemId = $items[0]['sale_item_id'];

        // 2) Usar los ids descubiertos en el alta real → 201.
        $this->asUser($t->bodeguero)->postJson('/api/v1/sales-returns', [
            'invoice_id' => $invoice->id,
            'lines' => [['sale_item_id' => $saleItemId, 'quantity' => '2.000', 'reason_code' => 'otro', 'destination' => 'merma']],
        ])->assertCreated();

        $this->assertSame('2.000', (string) $item->refresh()->returned_quantity);
    }

    public function test_manipulacion_cantidad_superior_rechazada(): void
    {
        $t = $this->seedTenant('a');
        [$invoice, $item] = $this->invoiceWithLine($t, $t->branch1, $t->product, '5.000', '0.000', paymentType: 'credito', withCxC: true);

        // Intentar devolver 6 cuando lo devolvible es 5 → rechazo; no persiste devolución.
        $this->asUser($t->bodeguero)->postJson('/api/v1/sales-returns', [
            'invoice_id' => $invoice->id,
            'lines' => [['sale_item_id' => $item->id, 'quantity' => '6.000', 'reason_code' => 'otro', 'destination' => 'merma']],
        ])->assertStatus(422);

        $this->assertSame('0.000', (string) $item->refresh()->returned_quantity);
    }
}
