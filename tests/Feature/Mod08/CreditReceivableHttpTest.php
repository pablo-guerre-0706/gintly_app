<?php

declare(strict_types=1);

namespace Tests\Feature\Mod08;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\AccountReceivable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\ReceivablePayment;
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
 * MOD-08 · Ventas a crédito y Cuentas por Cobrar (HTTP e2e contra MySQL).
 *
 * Reconcilia el flujo ACTIVO completo: generación atómica de la CxC al facturar a
 * crédito, cupo con serialización y autorización ROL-01, abonos atómicos de 5
 * pasos (efectivo/tarjeta), doble asiento fiscal/trazable sin doble conteo,
 * sincronización de estados, anulación que conserva abonos, endpoints de crédito
 * del cliente, protección de baja con cartera viva, listados/Resources, cron de
 * vencidas e integridad del motor MySQL.
 */
final class CreditReceivableHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    /**
     * Autenticación canónica para el guard 'web' (Sanctum SPA). Recarga el usuario
     * desde MySQL y estrecha el tipo con instanceof al contrato exacto que exige
     * actingAs() (Illuminate\Contracts\Auth\Authenticatable), en vez de confiar en @var.
     */
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
     * Negocio sembrado por el observer (cliente genérico + regla fiscal estándar =
     * tax_rate 0.15). Una sucursal con bodega por defecto, catálogo base, un cliente
     * real con cupo configurable, una caja con sesión ABIERTA y un producto estándar.
     */
    private function seedTenant(string $slug, string $creditLimit = '100000.00'): object
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

        $owner    = $this->makeUser($business, RoleName::Owner);
        $admin    = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator);

        $branch    = $this->makeBranch($business, 'S1 '.$slug);
        $warehouse = $this->makeWarehouse($business, $branch, 'B1 '.$slug);

        $category = new Category(['name' => 'Cat '.$slug]);
        $category->business_id = $business->id;
        $category->save();

        $unit = new UnitOfMeasure(['name' => 'Unidad', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $customer = $this->makeCustomer((object) compact('business'), $creditLimit);

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

        $product = $this->makeProduct((object) compact('business', 'category', 'unit'), TaxClass::Standard, '100.00');

        return (object) compact(
            'business', 'owner', 'admin', 'operator',
            'branch', 'warehouse', 'category', 'unit', 'customer', 'register', 'session', 'product',
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

    private function makeCustomer(object $t, string $creditLimit = '100000.00', bool $active = true): Customer
    {
        $customer = new Customer([
            'name'            => 'Cliente '.(++self::$seq),
            'document_type'   => 'cedula',
            'document_number' => 'DOC-'.self::$seq,
            'credit_limit'    => $creditLimit,
            'is_active'       => $active,
        ]);
        $customer->business_id = $t->business->id;
        $customer->save();

        return $customer->refresh();
    }

    private function makeProduct(object $t, TaxClass $class, string $price = '100.00'): Product
    {
        $product = new Product([
            'category_id'      => $t->category->id,
            'unit_id'          => $t->unit->id,
            'sku'              => 'SKU-'.(++self::$seq),
            'name'             => 'Producto '.self::$seq,
            'type'             => ProductType::Service,
            'sale_price'       => $price,
            'cost'             => '10.00',
            'tracks_inventory' => false,
            'tax_class'        => $class->value,
            'is_active'        => true,
        ]);
        $product->business_id = $t->business->id;
        $product->save();

        return $product;
    }

    /** Abre una venta para el cliente dado → agrega la línea del producto base → confirma. */
    private function confirmedSaleFor(object $t, Customer $customer, string $qty = '1.000', ?Product $product = null): int
    {
        $product ??= $t->product;

        $saleId = $this->asUser($t->operator)->postJson('/api/v1/sales', [
            'branch_id'   => $t->branch->id,
            'customer_id' => $customer->id,
        ])->assertCreated()->json('data.id');

        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/items", [
            'product_id' => $product->id,
            'quantity'   => $qty,
        ])->assertCreated();

        $this->asUser($t->operator)->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        return (int) $saleId;
    }

    /** Emite una factura a crédito. $payments = pago inicial parcial opcional. */
    private function creditInvoice(
        object $t,
        int $saleId,
        User $actor,
        array $payments = [],
        bool $ownerAuthorized = false
    ): TestResponse {
        $payload = [
            'sale_ids'     => [$saleId],
            'payment_type' => 'credito',
        ];

        if ($payments !== []) {
            $payload['payments']        = $payments;
            $payload['cash_session_id'] = $t->session->id;
        }
        if ($ownerAuthorized) {
            $payload['owner_authorized'] = true;
        }

        return $this->asUser($actor)->postJson('/api/v1/invoices', $payload);
    }

    /** CxC generada por una factura (sin scope de tenant, para aserciones de prueba). */
    private function arFor(int $invoiceId): AccountReceivable
    {
        return AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoiceId)->firstOrFail();
    }

    /**
     * Invariante monetaria de los DOS libros de pagos:
     *   accounts_receivable.paid_amount = Σ receivable_payments.amount
     *                                   = invoice.paid_amount = Σ invoice_payments.amount.
     * Además: 1:1 estricto (cada invoice_payment de la factura referenciado como máximo una vez).
     */
    private function assertLedgerInvariant(int $invoiceId): void
    {
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($invoiceId);
        $ar = $this->arFor($invoiceId);

        $sumInvoicePayments = (string) DB::table('invoice_payments')->where('invoice_id', $invoiceId)->sum('amount');
        $sumReceivablePayments = (string) DB::table('receivable_payments')
            ->where('accounts_receivable_id', $ar->id)->sum('amount');

        $n = fn ($v): string => number_format((float) $v, 2, '.', '');

        $this->assertSame($n($invoice->paid_amount), $n($sumInvoicePayments), 'invoice.paid_amount ≠ Σ invoice_payments');
        $this->assertSame($n($ar->paid_amount), $n($sumReceivablePayments), 'ar.paid_amount ≠ Σ receivable_payments');
        $this->assertSame($n($invoice->paid_amount), $n($ar->paid_amount), 'invoice.paid_amount ≠ ar.paid_amount');
        $this->assertSame($n($sumInvoicePayments), $n($sumReceivablePayments), 'Σ invoice_payments ≠ Σ receivable_payments');

        // 1:1: ningún invoice_payment referenciado por más de un abono, ni abono sin enlace.
        $this->assertSame(0, (int) DB::table('receivable_payments')
            ->where('accounts_receivable_id', $ar->id)->whereNull('invoice_payment_id')->count());
        $dups = DB::table('receivable_payments')
            ->select('invoice_payment_id')->groupBy('invoice_payment_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $this->assertSame(0, $dups, 'Existen invoice_payment_id duplicados entre abonos.');
    }

    /** Sesión de caja CERRADA del mismo negocio (para probar el 409 del cobro en efectivo). */
    private function makeClosedSession(object $t): CashSession
    {
        $session = new CashSession();
        $session->forceFill([
            'business_id'      => $t->business->id,
            'cash_register_id' => $t->register->id,
            'opened_by'        => $t->operator->id,
            'status'           => 'cerrada',
            'opening_amount'   => '0.00',
            'opened_at'        => now()->subHour(),
            'closed_at'        => now(),
        ])->saveQuietly();

        return $session;
    }

    // =======================================================================
    // (1) Integración de facturación a crédito y generación de la CxC
    // =======================================================================

    public function test_factura_a_credito_genera_cxc_pendiente_atomica(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);

        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)
            ->assertCreated()
            ->assertJsonPath('data.payment_type', 'credito')
            ->assertJsonPath('data.payment_status', 'pendiente')
            ->json('data.id');

        // Una sola CxC por factura, con saldo íntegro derivado por el motor.
        $ar = $this->arFor($invoiceId);
        $this->assertSame('115.00', (string) $ar->total_amount);
        $this->assertSame('0.00', (string) $ar->paid_amount);
        $this->assertSame('115.00', (string) $ar->balance);
        $this->assertSame('pendiente', $ar->status->value);
        $this->assertSame(1, AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoiceId)->count());

        // Sin pago inicial no hay ningún asiento fiscal todavía.
        $this->assertDatabaseCount('invoice_payments', 0);
        $this->assertDatabaseCount('receivable_payments', 0);
    }

    public function test_factura_a_credito_con_pago_inicial_parcial(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer); // total 115

        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator, [
            ['method' => 'efectivo', 'amount' => '15.00'],
        ])->assertCreated()->assertJsonPath('data.payment_status', 'parcial')->json('data.id');

        $ar = $this->arFor($invoiceId);
        $this->assertSame('15.00', (string) $ar->paid_amount);
        $this->assertSame('100.00', (string) $ar->balance);
        $this->assertSame('parcial', $ar->status->value);

        // El pago inicial es asiento fiscal Y materializa su asiento de cartera 1:1 (integridad
        // de los dos libros): 1 invoice_payment + 1 receivable_payment enlazado, mismo importe.
        $this->assertDatabaseCount('invoice_payments', 1);
        $this->assertDatabaseCount('receivable_payments', 1);
        $ipId = InvoicePayment::withoutGlobalScopes()->where('invoice_id', $invoiceId)->value('id');
        $this->assertDatabaseHas('receivable_payments', [
            'accounts_receivable_id' => $ar->id,
            'invoice_payment_id'     => $ipId,
            'amount'                 => '15.00',
        ]);
        // El efectivo inicial entra a caja SOLO como 'venta' (no genera 'cobro_credito').
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id, 'category' => 'venta', 'amount' => '15.00',
        ]);
        $this->assertDatabaseMissing('cash_movements', ['category' => 'cobro_credito']);

        // Invariante de acumulados: crear el abono inicial NO re-incrementa paid_amount.
        $this->assertLedgerInvariant($invoiceId);
    }

    // =======================================================================
    // (2)(3) Cupo, serialización y autorización ROL-01
    // =======================================================================

    public function test_credito_a_cliente_generico_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        $generic = Customer::withoutGlobalScopes()->where('business_id', $t->business->id)->where('is_generic', true)->firstOrFail();

        $saleId = $this->confirmedSaleFor($t, $generic);

        $this->creditInvoice($t, $saleId, $t->operator)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_type']);
    }

    public function test_credito_a_cliente_inactivo_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        $inactive = $this->makeCustomer($t, '100000.00', active: false);
        $saleId = $this->confirmedSaleFor($t, $inactive);

        $this->creditInvoice($t, $saleId, $t->operator)
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_INVOICE_STATE');

        // Rollback: ni factura ni CxC.
        $this->assertDatabaseCount('accounts_receivables', 0);
        $this->assertDatabaseMissing('invoices', ['customer_id' => $inactive->id]);
    }

    public function test_cliente_sin_linea_de_credito_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        $noLine = $this->makeCustomer($t, '0.00');
        $saleId = $this->confirmedSaleFor($t, $noLine);

        $this->creditInvoice($t, $saleId, $t->operator)
            ->assertStatus(422)
            ->assertJsonPath('code', 'CREDIT_LIMIT_EXCEEDED');

        $this->assertDatabaseCount('accounts_receivables', 0);
    }

    public function test_credito_dentro_del_limite_se_aprueba(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '1000.00');
        $saleId = $this->confirmedSaleFor($t, $customer);

        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $this->assertSame('115.00', (string) $this->arFor($invoiceId)->balance);
    }

    public function test_exceder_el_limite_se_rechaza_con_cifras_auditables(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '100.00'); // total 115 > 100
        $saleId = $this->confirmedSaleFor($t, $customer);

        $this->creditInvoice($t, $saleId, $t->operator)
            ->assertStatus(422)
            ->assertJsonPath('code', 'CREDIT_LIMIT_EXCEEDED')
            ->assertJsonPath('limit', '100.00')
            ->assertJsonPath('exposure', '0.00');

        $this->assertDatabaseCount('accounts_receivables', 0);
    }

    public function test_owner_authorized_de_un_no_owner_no_autoriza_el_exceso(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '100.00');
        $saleId = $this->confirmedSaleFor($t, $customer);

        // ROL-03 envía owner_authorized=true: el request lo coacciona a false (solo ROL-01).
        $this->creditInvoice($t, $saleId, $t->operator, ownerAuthorized: true)
            ->assertStatus(422)
            ->assertJsonPath('code', 'CREDIT_LIMIT_EXCEEDED');

        $this->assertDatabaseCount('accounts_receivables', 0);
    }

    public function test_owner_authorized_de_rol01_autoriza_el_exceso(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '100.00');
        $saleId = $this->confirmedSaleFor($t, $customer);

        // ROL-01 autoriza explícitamente exceder el cupo → CxC creada.
        $invoiceId = $this->creditInvoice($t, $saleId, $t->owner, ownerAuthorized: true)
            ->assertCreated()->json('data.id');

        $this->assertSame('115.00', (string) $this->arFor($invoiceId)->balance);
    }

    public function test_exposicion_acumulada_serializa_el_cupo(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '200.00');

        // 1ª factura 115 dentro del cupo (0 + 115 <= 200).
        $sale1 = $this->confirmedSaleFor($t, $customer);
        $this->creditInvoice($t, $sale1, $t->operator)->assertCreated();

        // 2ª factura 115: la exposición ya es 115; 115 + 115 = 230 > 200 → rechazo.
        // El lock del cliente en InvoiceService serializa esto bajo concurrencia real.
        $sale2 = $this->confirmedSaleFor($t, $customer);
        $this->creditInvoice($t, $sale2, $t->operator)
            ->assertStatus(422)
            ->assertJsonPath('code', 'CREDIT_LIMIT_EXCEEDED')
            ->assertJsonPath('exposure', '115.00');

        $this->assertSame(1, AccountReceivable::withoutGlobalScopes()->where('customer_id', $customer->id)->count());
    }

    // =======================================================================
    // (4)(6)(7) Abonos atómicos, doble asiento y sincronización de estados
    // =======================================================================

    public function test_abono_parcial_actualiza_saldo_y_estado(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount'          => '15.00',
            'payment_method'  => 'efectivo',
            'cash_session_id' => $t->session->id,
        ])->assertCreated()
            ->assertJsonPath('data.amount', '15.00')
            ->assertJsonPath('data.account_receivable.balance', '100.00')
            ->assertJsonPath('data.account_receivable.status', 'parcial');

        $ar->refresh();
        $this->assertSame('100.00', (string) $ar->balance);
        $this->assertSame('parcial', $ar->status->value);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'payment_status' => 'parcial']);
    }

    public function test_abono_total_salda_la_cuenta_y_sincroniza_la_factura(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount'          => '115.00',
            'payment_method'  => 'efectivo',
            'cash_session_id' => $t->session->id,
        ])->assertCreated()->assertJsonPath('data.account_receivable.status', 'pagada');

        $ar->refresh();
        $this->assertSame('0.00', (string) $ar->balance);
        $this->assertSame('pagada', $ar->status->value);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'payment_status' => 'pagada']);
    }

    public function test_sobreabono_se_rechaza_y_revierte(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount'          => '200.00',
            'payment_method'  => 'efectivo',
            'cash_session_id' => $t->session->id,
        ])->assertStatus(422)->assertJsonPath('code', 'OVERPAYMENT');

        // Rollback total de los 5 pasos: nada persistido, saldo intacto.
        $this->assertDatabaseCount('receivable_payments', 0);
        $this->assertDatabaseCount('invoice_payments', 0);
        $this->assertDatabaseMissing('cash_movements', ['category' => 'cobro_credito']);
        $this->assertSame('115.00', (string) $ar->refresh()->balance);
    }

    public function test_abono_sobre_cuenta_saldada_se_rechaza(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $abono = fn (string $amount) => $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => $amount, 'payment_method' => 'transferencia',
        ]);

        $abono('115.00')->assertCreated();
        $abono('1.00')->assertStatus(422)->assertJsonPath('code', 'OVERPAYMENT');
    }

    // =======================================================================
    // (4) Impacto en caja según el medio de pago
    // =======================================================================

    public function test_abono_en_efectivo_genera_movimiento_cobro_credito(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount'          => '50.00',
            'payment_method'  => 'efectivo',
            'cash_session_id' => $t->session->id,
        ])->assertCreated();

        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $t->session->id,
            'category'        => 'cobro_credito',
            'payment_method'  => 'efectivo',
            'amount'          => '50.00',
        ]);
    }

    public function test_abono_por_tarjeta_o_transferencia_no_toca_la_caja(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount'         => '50.00',
            'payment_method' => 'tarjeta',
        ])->assertCreated()->assertJsonPath('data.cash_session_id', null);

        // No hay movimiento de caja; el abono sí queda registrado y baja el saldo.
        $this->assertDatabaseMissing('cash_movements', ['category' => 'cobro_credito']);
        $this->assertSame('65.00', (string) $ar->refresh()->balance);
        $this->assertDatabaseHas('receivable_payments', [
            'accounts_receivable_id' => $ar->id, 'payment_method' => 'tarjeta', 'cash_session_id' => null,
        ]);
    }

    public function test_abono_en_efectivo_sobre_caja_cerrada_da_409(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);
        $closed = $this->makeClosedSession($t);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount'          => '10.00',
            'payment_method'  => 'efectivo',
            'cash_session_id' => $closed->id,
        ])->assertStatus(409)->assertJsonPath('error', 'NO_ACTIVE_CASH_SESSION');

        // Atomicidad: ni abono ni asiento fiscal.
        $this->assertDatabaseCount('receivable_payments', 0);
        $this->assertDatabaseCount('invoice_payments', 0);
        $this->assertSame('115.00', (string) $ar->refresh()->balance);
    }

    public function test_abono_en_efectivo_sin_sesion_da_422(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount'         => '10.00',
            'payment_method' => 'efectivo',
        ])->assertStatus(422)->assertJsonValidationErrors(['cash_session_id']);
    }

    public function test_abono_de_monto_no_positivo_da_422(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '0.00', 'payment_method' => 'transferencia',
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    // =======================================================================
    // (6) Relación invoice_payments ↔ receivable_payments (sin doble conteo)
    // =======================================================================

    public function test_abono_crea_asiento_fiscal_enlazado_y_conserva_invariante(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '40.00', 'payment_method' => 'transferencia',
        ])->assertCreated();

        // El abono (fuente trazable) apunta 1:1 a un asiento fiscal (fuente fiscal única).
        $payment = ReceivablePayment::withoutGlobalScopes()->where('accounts_receivable_id', $ar->id)->firstOrFail();
        $this->assertNotNull($payment->invoice_payment_id);
        $this->assertDatabaseHas('invoice_payments', [
            'id' => $payment->invoice_payment_id, 'invoice_id' => $invoiceId, 'amount' => '40.00',
        ]);

        // Invariante fiscal: invoice.paid_amount == Σ invoice_payments (sin doble conteo).
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($invoiceId);
        $sumFiscal = (string) DB::table('invoice_payments')->where('invoice_id', $invoiceId)->sum('amount');
        $this->assertSame(
            number_format((float) $invoice->paid_amount, 2, '.', ''),
            number_format((float) $sumFiscal, 2, '.', ''),
        );

        // El abono aparece en el libro fiscal de la factura (RF-07).
        $this->asUser($t->admin)->getJson("/api/v1/invoices/{$invoiceId}/payments")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.amount', '40.00');
    }

    public function test_invariante_de_los_dos_libros_en_todos_los_escenarios(): void
    {
        $t = $this->seedTenant('a');
        $cs = $t->session->id;

        // (a) Emisión a crédito SIN pago inicial.
        $s1 = $this->confirmedSaleFor($t, $t->customer);
        $inv1 = $this->creditInvoice($t, $s1, $t->operator)->assertCreated()->json('data.id');
        $this->assertLedgerInvariant($inv1); // 0 == 0 == 0 == 0

        // (b) Emisión a crédito CON pago inicial parcial (materializa asiento de cartera 1:1).
        $s2 = $this->confirmedSaleFor($t, $t->customer);
        $inv2 = $this->creditInvoice($t, $s2, $t->operator, [
            ['method' => 'efectivo', 'amount' => '15.00'],
        ])->assertCreated()->json('data.id');
        $this->assertLedgerInvariant($inv2);

        // (c) Abono PARCIAL sobre inv1.
        $ar1 = $this->arFor($inv1);
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar1->id}/payments", [
            'amount' => '40.00', 'payment_method' => 'transferencia',
        ])->assertCreated();
        $this->assertLedgerInvariant($inv1);

        // (d) Abono TOTAL sobre inv1 (salda).
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar1->id}/payments", [
            'amount' => '75.00', 'payment_method' => 'transferencia',
        ])->assertCreated();
        $this->assertLedgerInvariant($inv1);
        $this->assertSame('pagada', $ar1->refresh()->status->value);

        // (e) Pago MIXTO inicial (efectivo + tarjeta) en una factura a crédito → 2 asientos 1:1.
        $s3 = $this->confirmedSaleFor($t, $t->customer);
        $inv3 = $this->creditInvoice($t, $s3, $t->operator, [
            ['method' => 'efectivo', 'amount' => '15.00'],
            ['method' => 'tarjeta', 'amount' => '35.00'],
        ])->assertCreated()->json('data.id');
        $this->assertSame(2, (int) DB::table('receivable_payments')
            ->where('accounts_receivable_id', $this->arFor($inv3)->id)->count());
        $this->assertLedgerInvariant($inv3);

        // (f) Anulación conservando abonos: la invariante de acumulados se mantiene.
        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$inv2}/void", ['void_reason' => 'Error de emisión'])->assertOk();
        $this->assertLedgerInvariant($inv2);
    }

    public function test_unique_del_motor_impide_dos_abonos_por_asiento_fiscal(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator, [
            ['method' => 'transferencia', 'amount' => '15.00'],
        ])->assertCreated()->json('data.id');

        $rp = ReceivablePayment::withoutGlobalScopes()->where('accounts_receivable_id', $this->arFor($invoiceId)->id)->firstOrFail();

        // Un segundo receivable_payment apuntando al MISMO invoice_payment viola uniq_rp_invoice_payment.
        try {
            DB::table('receivable_payments')->insert([
                'business_id'            => $rp->business_id,
                'accounts_receivable_id' => $rp->accounts_receivable_id,
                'invoice_payment_id'     => $rp->invoice_payment_id, // duplicado deliberado
                'cash_session_id'        => null,
                'user_id'                => $rp->user_id,
                'payment_method'         => 'transferencia',
                'amount'                 => '15.00',
                'paid_at'                => now(),
                'created_at'             => now(),
            ]);
            $this->fail('El motor debía rechazar dos abonos con el mismo invoice_payment_id.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(1062, (int) ($e->errorInfo[1] ?? 0)); // duplicate key
        }
    }

    // =======================================================================
    // (8) Anulación de la factura: conserva abonos y salda sin borrar
    // =======================================================================

    public function test_anulacion_conserva_abonos_y_salda_la_cxc_sin_borrar(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '50.00', 'payment_method' => 'transferencia',
        ])->assertCreated();

        // ROL-01 anula la factura.
        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'Error de emisión'])
            ->assertOk()->assertJsonPath('data.status', 'anulada');

        // La CxC no se borra: total baja a lo abonado, saldo 0, estado pagada. Abonos intactos.
        $ar->refresh();
        $this->assertSame('50.00', (string) $ar->total_amount);
        $this->assertSame('50.00', (string) $ar->paid_amount);
        $this->assertSame('0.00', (string) $ar->balance);
        $this->assertSame('pagada', $ar->status->value);
        $this->assertDatabaseCount('receivable_payments', 1);
    }

    public function test_abono_sobre_factura_anulada_da_409(): void
    {
        $t = $this->seedTenant('a');
        $saleId = $this->confirmedSaleFor($t, $t->customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->owner)->postJson("/api/v1/invoices/{$invoiceId}/void", ['void_reason' => 'Anulada'])->assertOk();

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '10.00', 'payment_method' => 'transferencia',
        ])->assertStatus(409)->assertJsonPath('error', 'INVOICE_VOIDED');
    }

    // =======================================================================
    // (9) Endpoints de crédito del cliente
    // =======================================================================

    public function test_credit_status_expone_cupo_exposicion_e_historial(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '500.00');
        $saleId = $this->confirmedSaleFor($t, $customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '15.00', 'payment_method' => 'transferencia',
        ])->assertCreated();

        // ROL-02+ ve el estado de crédito consolidado (decimales como string).
        $this->asUser($t->admin)->getJson("/api/v1/customers/{$customer->id}/credit-status")
            ->assertOk()
            ->assertJsonPath('data.credit_limit', '500.00')
            ->assertJsonPath('data.exposure', '100.00')      // 115 - 15
            ->assertJsonPath('data.available_credit', '400.00')
            ->assertJsonPath('data.open_accounts.0.balance', '100.00')
            ->assertJsonPath('data.payment_history.0.amount', '15.00');
    }

    public function test_credit_check_evalua_sin_persistir(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '200.00');

        // Dentro del cupo.
        $this->asUser($t->operator)->postJson("/api/v1/customers/{$customer->id}/credit-check", ['amount' => '150.00'])
            ->assertOk()
            ->assertJsonPath('data.approved', true)
            ->assertJsonPath('data.requires_owner_authorization', false)
            ->assertJsonPath('data.available', '200.00');

        // Excede el cupo → requiere autorización ROL-01, sin aprobar.
        $this->asUser($t->operator)->postJson("/api/v1/customers/{$customer->id}/credit-check", ['amount' => '250.00'])
            ->assertOk()
            ->assertJsonPath('data.approved', false)
            ->assertJsonPath('data.requires_owner_authorization', true);

        // No se creó ninguna CxC por evaluar.
        $this->assertDatabaseCount('accounts_receivables', 0);
    }

    public function test_cliente_con_cartera_viva_no_puede_darse_de_baja(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '500.00');
        $saleId = $this->confirmedSaleFor($t, $customer);
        $this->creditInvoice($t, $saleId, $t->operator)->assertCreated();

        // Desactivar (PUT is_active=false) con CxC pendiente → 422 CUSTOMER_HAS_RECEIVABLES.
        $this->asUser($t->admin)->putJson("/api/v1/customers/{$customer->id}", ['is_active' => false])
            ->assertStatus(422)->assertJsonPath('code', 'CUSTOMER_HAS_RECEIVABLES');

        // Borrado lógico igualmente bloqueado.
        $this->asUser($t->admin)->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertStatus(422)->assertJsonPath('code', 'CUSTOMER_HAS_RECEIVABLES');

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => 1, 'deleted_at' => null]);
    }

    public function test_cliente_saldado_puede_darse_de_baja(): void
    {
        $t = $this->seedTenant('a');
        $customer = $this->makeCustomer($t, '500.00');
        $saleId = $this->confirmedSaleFor($t, $customer);
        $invoiceId = $this->creditInvoice($t, $saleId, $t->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '115.00', 'payment_method' => 'transferencia',
        ])->assertCreated();

        // Saldada la cartera, la baja lógica procede.
        $this->asUser($t->admin)->putJson("/api/v1/customers/{$customer->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
    }

    // =======================================================================
    // (10) Listados, filtros, paginación y Resources
    // =======================================================================

    public function test_listado_filtra_pagina_e_ignora_orden_arbitrario(): void
    {
        $t = $this->seedTenant('a');

        // CxC #1 pendiente.
        $sale1 = $this->confirmedSaleFor($t, $t->customer);
        $inv1  = $this->creditInvoice($t, $sale1, $t->operator)->assertCreated()->json('data.id');

        // CxC #2 saldada.
        $sale2 = $this->confirmedSaleFor($t, $t->customer);
        $inv2  = $this->creditInvoice($t, $sale2, $t->operator)->assertCreated()->json('data.id');
        $ar2   = $this->arFor($inv2);
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$ar2->id}/payments", [
            'amount' => '115.00', 'payment_method' => 'transferencia',
        ])->assertCreated();

        // ROL-03 no puede listar la cartera (viewAny = ROL-02+).
        $this->asUser($t->operator)->getJson('/api/v1/accounts-receivable')->assertStatus(403);

        // Listado completo: 2 cuentas, decimales como string, paginado.
        $this->asUser($t->admin)->getJson('/api/v1/accounts-receivable')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.balance', fn ($v) => is_string($v));

        // Filtro por estado 'pendiente' + paginación → solo la #1.
        $this->asUser($t->admin)->getJson('/api/v1/accounts-receivable?status=pendiente&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_id', $inv1);

        // Orden arbitrario: la request NO admite ninguna columna de sort (sortableColumns=[]),
        // así que un 'sort' inyectado se RECHAZA con 422 (no se ordena por un campo cualquiera).
        $this->asUser($t->admin)->getJson('/api/v1/accounts-receivable?sort=balance')
            ->assertStatus(422)->assertJsonValidationErrors(['sort']);
    }

    // =======================================================================
    // (Aislamiento) Multitenant y bindings de Customers
    // =======================================================================

    public function test_aislamiento_entre_negocios_y_bindings(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');

        $saleId = $this->confirmedSaleFor($a, $a->customer);
        $invoiceId = $this->creditInvoice($a, $saleId, $a->operator)->assertCreated()->json('data.id');
        $ar = $this->arFor($invoiceId);

        // El negocio B no ve la CxC de A (BusinessScope → 404).
        $this->asUser($b->admin)->getJson("/api/v1/accounts-receivable/{$ar->id}")->assertNotFound();
        // Ni puede abonarla.
        $this->asUser($b->operator)->postJson("/api/v1/accounts-receivable/{$ar->id}/payments", [
            'amount' => '10.00', 'payment_method' => 'transferencia',
        ])->assertNotFound();
        // Ni consultar el crédito del cliente de A.
        $this->asUser($b->admin)->getJson("/api/v1/customers/{$a->customer->id}/credit-status")->assertNotFound();

        // El abono de A no se filtró.
        $this->assertDatabaseCount('receivable_payments', 0);
    }

    // =======================================================================
    // (11) Comando de vencimiento e idempotencia
    // =======================================================================

    public function test_comando_marca_vencidas_solo_las_procedentes_y_es_idempotente(): void
    {
        $t = $this->seedTenant('a');

        // CxC vencida: pendiente con vencimiento pasado y saldo > 0.
        $saleVenc = $this->confirmedSaleFor($t, $t->customer);
        $invVenc  = $this->creditInvoice($t, $saleVenc, $t->operator)->assertCreated()->json('data.id');
        $arVenc   = $this->arFor($invVenc);
        AccountReceivable::withoutGlobalScopes()->whereKey($arVenc->id)
            ->update(['due_date' => now()->subDays(5)->toDateString()]);

        // CxC saldada con vencimiento pasado: NO debe marcarse (saldo 0).
        $salePag = $this->confirmedSaleFor($t, $t->customer);
        $invPag  = $this->creditInvoice($t, $salePag, $t->operator)->assertCreated()->json('data.id');
        $arPag   = $this->arFor($invPag);
        $this->asUser($t->operator)->postJson("/api/v1/accounts-receivable/{$arPag->id}/payments", [
            'amount' => '115.00', 'payment_method' => 'transferencia',
        ])->assertCreated();
        AccountReceivable::withoutGlobalScopes()->whereKey($arPag->id)
            ->update(['due_date' => now()->subDays(5)->toDateString()]);

        // CxC pendiente pero VIGENTE (vence en el futuro): tampoco se marca.
        $saleVig = $this->confirmedSaleFor($t, $t->customer);
        $invVig  = $this->creditInvoice($t, $saleVig, $t->operator)->assertCreated()->json('data.id');
        $arVig   = $this->arFor($invVig);

        $this->artisan('receivables:mark-overdue')->assertExitCode(0);

        $this->assertSame('vencida', $arVenc->refresh()->status->value);
        $this->assertSame('pagada', $arPag->refresh()->status->value);
        $this->assertSame('pendiente', $arVig->refresh()->status->value);

        // Idempotente: una 2ª corrida no altera nada ni duplica.
        $this->artisan('receivables:mark-overdue')->assertExitCode(0);
        $this->assertSame('vencida', $arVenc->refresh()->status->value);
        $this->assertSame('pagada', $arPag->refresh()->status->value);
    }

    // =======================================================================
    // (12) Integridad del motor MySQL (information_schema)
    // =======================================================================

    public function test_integridad_de_esquema_mysql_de_la_cxc(): void
    {
        $db = DB::getDatabaseName();

        // balance es columna GENERADA (no editable por la aplicación).
        $balance = DB::selectOne(
            'SELECT EXTRA, GENERATION_EXPRESSION AS expr, DATA_TYPE, NUMERIC_PRECISION AS p, NUMERIC_SCALE AS s
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'accounts_receivables', 'balance'],
        );
        $this->assertNotNull($balance);
        $this->assertStringContainsString('GENERATED', strtoupper((string) $balance->EXTRA));
        $this->assertSame('decimal', strtolower((string) $balance->DATA_TYPE));
        $this->assertSame(2, (int) $balance->s);

        // UNIQUE de una CxC por factura (uniq_ar_invoice).
        $uniqueInvoice = DB::selectOne(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$db, 'accounts_receivables', 'uniq_ar_invoice'],
        );
        $this->assertNotNull($uniqueInvoice, 'Falta el índice único uniq_ar_invoice.');
        $this->assertSame(0, (int) $uniqueInvoice->NON_UNIQUE);

        // CHECK de saldo no negativo.
        $check = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ? AND CONSTRAINT_NAME = ?',
            [$db, 'accounts_receivables', 'CHECK', 'chk_ar_balance_non_negative'],
        );
        $this->assertSame(1, (int) $check->c, 'Falta el CHECK chk_ar_balance_non_negative.');

        // FK de la CxC hacia la factura con borrado RESTRINGIDO (no cascada).
        $fk = DB::selectOne(
            "SELECT rc.DELETE_RULE AS del
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = ? AND rc.TABLE_NAME = ? AND kcu.COLUMN_NAME = ?",
            [$db, 'accounts_receivables', 'invoice_id'],
        );
        $this->assertNotNull($fk);
        $this->assertSame('RESTRICT', strtoupper((string) $fk->del));

        // FK del abono fiscal enlazado (invoice_payment_id) también RESTRICT.
        $fkRp = DB::selectOne(
            "SELECT rc.DELETE_RULE AS del
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = ? AND rc.TABLE_NAME = ? AND kcu.COLUMN_NAME = ?",
            [$db, 'receivable_payments', 'invoice_payment_id'],
        );
        $this->assertNotNull($fkRp, 'Falta la FK receivable_payments.invoice_payment_id.');
        $this->assertSame('RESTRICT', strtoupper((string) $fkRp->del));

        // UNIQUE(invoice_payment_id): 1:1 garantizado por el motor (no solo Eloquent).
        $uniqueLink = DB::selectOne(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$db, 'receivable_payments', 'uniq_rp_invoice_payment'],
        );
        $this->assertNotNull($uniqueLink, 'Falta el índice único uniq_rp_invoice_payment.');
        $this->assertSame(0, (int) $uniqueLink->NON_UNIQUE);

        // invoice_payment_id NOT NULL definitivo: todo abono debe tener asiento fiscal.
        $linkCol = DB::selectOne(
            'SELECT IS_NULLABLE, DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'receivable_payments', 'invoice_payment_id'],
        );
        $this->assertNotNull($linkCol);
        $this->assertSame('NO', strtoupper((string) $linkCol->IS_NULLABLE));

        // Sin enlaces nulos ni duplicados en los datos.
        $this->assertSame(0, (int) DB::table('receivable_payments')->whereNull('invoice_payment_id')->count());
        $this->assertSame(0, DB::table('receivable_payments')
            ->select('invoice_payment_id')->groupBy('invoice_payment_id')->havingRaw('COUNT(*) > 1')->get()->count());
    }
}
