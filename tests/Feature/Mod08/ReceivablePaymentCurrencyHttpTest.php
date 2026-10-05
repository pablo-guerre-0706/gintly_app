<?php

declare(strict_types=1);

namespace Tests\Feature\Mod08;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\AccountReceivable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ReceivablePayment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\EquipsOperativeProfiles;
use Tests\MysqlTestCase;

/**
 * MOD-08/06 · Abonos de CxC con doble moneda NIO/USD. La cuenta se denomina en NIO; un abono en USD
 * conserva moneda nativa + tasa snapshot + equivalente NIO (base_amount), amortiza el saldo por su
 * equivalente NIO y, en efectivo, asienta un cash_movement 'cobro_credito' en su moneda. NIO intacto.
 */
final class ReceivablePaymentCurrencyHttpTest extends MysqlTestCase
{
    use EquipsOperativeProfiles;

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
        $this->app['auth']->forgetGuards();
        $r = app(PermissionRegistrar::class);
        $r->setPermissionsTeamId(null);
        $r->forgetCachedPermissions();
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

        $customer = new Customer(['name' => 'Cliente '.(++self::$seq), 'document_type' => 'cedula', 'document_number' => 'DOC-'.self::$seq, 'credit_limit' => '100000.00', 'is_active' => true]);
        $customer->business_id = $business->id;
        $customer->save();

        $register = new CashRegister();
        $register->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Caja '.$slug, 'is_active' => true])->save();

        $session = new CashSession();
        $session->forceFill(['business_id' => $business->id, 'cash_register_id' => $register->id, 'opened_by' => $operator->id, 'status' => 'abierta', 'opening_amount' => '0.00', 'opened_at' => now()])->saveQuietly();

        $product = new Product([
            'category_id' => $category->id, 'unit_id' => $unit->id, 'sku' => 'SKU-'.(++self::$seq),
            'name' => 'Producto '.self::$seq, 'type' => ProductType::Service, 'sale_price' => '100.00', 'cost' => '10.00',
            'tracks_inventory' => false, 'tax_class' => TaxClass::Standard->value, 'is_active' => true,
        ]);
        $product->business_id = $business->id;
        $product->save();

        return (object) compact('business', 'owner', 'operator', 'branch', 'warehouse', 'category', 'unit', 'customer', 'register', 'session', 'product');
    }

    private function makeUser(Business $business, RoleName $role): User
    {
        $u = new User(['name' => $role->value.' '.(++self::$seq), 'email' => 'u'.self::$seq.'@test.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true]);
        $u->business_id = $business->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $u->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $u;
    }

    private function makeBranch(Business $business, string $name): Branch
    {
        $b = new Branch();
        $b->forceFill(['business_id' => $business->id, 'name' => $name, 'address' => 'Dir', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $b;
    }

    private function makeWarehouse(Business $business, Branch $branch, string $name): Warehouse
    {
        $w = new Warehouse();
        $w->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => $name, 'is_default' => true, 'is_active' => true])->save();

        return $w;
    }

    private function seedRate(object $t, string $currency, string $rate): void
    {
        $row = new ExchangeRate;
        $row->forceFill(['business_id' => $t->business->id, 'currency' => $currency, 'rate' => $rate, 'effective_from' => Carbon::now()->subDay(), 'created_by' => $t->owner->id])->save();
    }

    /** Factura a crédito total 115 NIO → devuelve la CxC. */
    private function creditAr(object $t): AccountReceivable
    {
        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id' => $t->branch->id, 'customer_id' => $t->customer->id,
        ])->assertCreated()->json('data.id');
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", ['product_id' => $t->product->id, 'quantity' => '1.000'])->assertCreated();
        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        $invoiceId = $this->asUser($t->operator)->postJson('/api/v1/invoices', [
            'sale_ids' => [$saleId], 'payment_type' => 'credito',
        ])->assertCreated()->json('data.id');

        return AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoiceId)->firstOrFail();
    }

    // ---------------- Abono USD en efectivo ----------------

    public function test_abono_usd_efectivo_congela_tasa_y_amortiza_equivalente_nio(): void
    {
        $t = $this->seedTenant('cxc');
        $this->seedRate($t, 'USD', '13.00');
        $ar = $this->creditAr($t); // saldo 115 NIO

        // Abona 5 USD (=65 NIO) en efectivo.
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '5.00', 'currency' => 'USD', 'payment_method' => 'efectivo', 'cash_session_id' => $t->session->id,
        ])->assertCreated()
            ->assertJsonPath('data.amount', '5.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.exchange_rate', '13.000000')
            ->assertJsonPath('data.base_amount', '65.00')
            ->assertJsonPath('data.account_receivable.balance', '50.00')   // 115 − 65
            ->assertJsonPath('data.account_receivable.status', 'parcial');

        $ar->refresh();
        $this->assertSame('65.00', (string) $ar->paid_amount); // equivalente NIO
        $this->assertSame('50.00', (string) $ar->balance);

        // Movimiento de caja cobro_credito en USD: nativo 5, base 65, tasa 13.
        $mov = CashMovement::withoutGlobalScopes()->where('cash_session_id', $t->session->id)->where('category', 'cobro_credito')->firstOrFail();
        $this->assertSame('5.00', (string) $mov->amount);
        $this->assertSame('USD', $mov->currency->value);
        $this->assertSame('65.00', (string) $mov->base_amount);
        $this->assertSame(0, bccomp((string) $mov->exchange_rate, '13', 6));

        // Asiento fiscal (invoice_payment) y de cartera (receivable_payment) en USD, enlazados 1:1.
        $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $ar->invoice_id, 'amount' => '5.00', 'currency' => 'USD', 'base_amount' => '65.00']);
        $this->assertDatabaseHas('receivable_payments', ['accounts_receivable_id' => $ar->id, 'amount' => '5.00', 'currency' => 'USD', 'base_amount' => '65.00']);

        // Invariante en EQUIVALENTE NIO: Σ base_amount == paid_amount (ambos libros).
        $sumRpBase = (string) DB::table('receivable_payments')->where('accounts_receivable_id', $ar->id)->sum('base_amount');
        $sumIpBase = (string) DB::table('invoice_payments')->where('invoice_id', $ar->invoice_id)->sum('base_amount');
        $n = fn ($v): string => number_format((float) $v, 2, '.', '');
        $this->assertSame($n($ar->paid_amount), $n($sumRpBase));
        $this->assertSame($n($ar->paid_amount), $n($sumIpBase));
    }

    public function test_abono_mixto_usd_luego_nio_salda_la_cuenta(): void
    {
        $t = $this->seedTenant('cxc');
        $this->seedRate($t, 'USD', '13.00');
        $ar = $this->creditAr($t); // 115 NIO

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '5.00', 'currency' => 'USD', 'payment_method' => 'efectivo', 'cash_session_id' => $t->session->id,
        ])->assertCreated(); // −65 → 50

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '50.00', 'payment_method' => 'transferencia', // NIO por defecto
        ])->assertCreated()->assertJsonPath('data.account_receivable.status', 'pagada');

        $this->assertSame('0.00', (string) $ar->refresh()->balance);
    }

    // ---------------- Compatibilidad NIO ----------------

    public function test_abono_nio_sin_moneda_conserva_comportamiento(): void
    {
        $t = $this->seedTenant('cxc');
        $ar = $this->creditAr($t);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '40.00', 'payment_method' => 'transferencia',
        ])->assertCreated()
            ->assertJsonPath('data.currency', 'NIO')
            ->assertJsonPath('data.exchange_rate', '1.000000')
            ->assertJsonPath('data.base_amount', '40.00')
            ->assertJsonPath('data.account_receivable.balance', '75.00');
    }

    // ---------------- Rechazos controlados ----------------

    public function test_abono_usd_sin_tasa_vigente_rechaza_y_revierte(): void
    {
        $t = $this->seedTenant('cxc');
        $ar = $this->creditAr($t); // sin tasa USD configurada

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '5.00', 'currency' => 'USD', 'payment_method' => 'efectivo', 'cash_session_id' => $t->session->id,
        ])->assertStatus(422)->assertJsonPath('code', 'EXCHANGE_RATE_MISSING');

        // Rollback total: ni abono, ni asiento fiscal, ni movimiento de caja; saldo intacto.
        $this->assertSame(0, ReceivablePayment::withoutGlobalScopes()->where('accounts_receivable_id', $ar->id)->count());
        $this->assertSame(0, CashMovement::withoutGlobalScopes()->where('category', 'cobro_credito')->count());
        $this->assertSame('115.00', (string) $ar->refresh()->balance);
    }

    public function test_sobreabono_por_equivalente_nio_rechaza(): void
    {
        $t = $this->seedTenant('cxc');
        $this->seedRate($t, 'USD', '13.00');
        $ar = $this->creditAr($t); // 115 NIO

        // 10 USD = 130 NIO > 115 de saldo → OVERPAYMENT (se evalúa por equivalente NIO).
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '10.00', 'currency' => 'USD', 'payment_method' => 'efectivo', 'cash_session_id' => $t->session->id,
        ])->assertStatus(422)->assertJsonPath('code', 'OVERPAYMENT');

        $this->assertSame('115.00', (string) $ar->refresh()->balance);
        $this->assertSame(0, CashMovement::withoutGlobalScopes()->where('category', 'cobro_credito')->count());
    }
}
