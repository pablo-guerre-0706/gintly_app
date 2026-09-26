<?php

declare(strict_types=1);

namespace Tests\Feature\Mod12;

use App\Enums\RoleName;
use App\Models\AccountReceivable;
use App\Models\Anomaly;
use App\Models\AnomalyRule;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessGoal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\KpiSnapshot;
use App\Models\PhysicalCount;
use App\Models\Product;
use App\Models\Category;
use App\Models\ReportDefinition;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-12 · Reportería, KPIs e Inteligencia de Negocios (HTTP e2e contra MySQL).
 *
 * Cubre metas (CUD ROL-01, unicidad global/sucursal, identidad inmutable, meta congelada),
 * el registro canónico, las fórmulas KPI-01..08 (con corte de período en business.timezone y
 * límites UTC), KPI-06 agregador al final, dirección up/down, KPI-08 acotado por período,
 * idempotencia y atomicidad del recálculo, panel, reportes solo-lectura comparables, definiciones
 * de reporte, el comando multi-tenant sin sesión, el aislamiento por negocio y el esquema.
 */
final class ReportKpiHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    /** Zona no-UTC del negocio: Managua = UTC-06:00 (sin DST). */
    private const TZ = 'America/Managua';

    // ============================ Infraestructura ============================

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $authenticated = User::query()->whereKey($u->getKey())->firstOrFail();
        if (! $authenticated instanceof AuthenticatableContract) {
            throw new \RuntimeException('El usuario recargado no implementa Authenticatable.');
        }

        return $this->actingAs($authenticated, 'web');
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
            'timezone' => self::TZ,
        ]);

        $branch = new Branch();
        $branch->forceFill([
            'business_id' => $business->id,
            'name'        => 'Sucursal '.$slug,
            'address'     => 'Dir. '.$slug,
            'opened_at'   => now()->toDateString(),
            'is_active'   => true,
        ])->saveQuietly();

        $warehouse = new Warehouse();
        $warehouse->forceFill([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Bodega '.$slug,
            'is_default'  => true,
            'is_active'   => true,
        ])->save();

        $owner    = $this->makeUser($business, RoleName::Owner);
        $admin    = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator, $branch);

        $category = new Category(['name' => 'Cat '.$slug]);
        $category->business_id = $business->id;
        $category->save();

        $unit = new UnitOfMeasure(['name' => 'Unidad', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $customer = new Customer(['name' => 'Cliente '.$slug, 'is_active' => true]);
        $customer->business_id = $business->id;
        $customer->save();

        return (object) compact('business', 'branch', 'warehouse', 'owner', 'admin', 'operator', 'category', 'unit', 'customer');
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

    private function makeProduct(object $t): Product
    {
        $product = new Product([
            'category_id'      => $t->category->id,
            'unit_id'          => $t->unit->id,
            'sku'              => 'SKU-'.(++self::$seq),
            'name'             => 'Producto '.self::$seq,
            'type'             => 'simple',
            'sale_price'       => '10.00',
            'cost'             => '4.0000',
            'tracks_inventory' => true,
            'tax_class'        => 'exempt',
            'is_active'        => true,
        ]);
        $product->business_id = $t->business->id;
        $product->save();

        return $product;
    }

    /** Factura emitida con issued_at UTC explícito (sucursal opcional; por defecto la principal). */
    private function invoice(object $t, string $issuedAtUtc, string $total, string $paymentType = 'contado', string $paidAmount = null, ?int $branchId = null): Invoice
    {
        $paid = $paidAmount ?? ($paymentType === 'contado' ? $total : '0.00');
        $inv = new Invoice();
        $inv->forceFill([
            'business_id'     => $t->business->id,
            'branch_id'       => $branchId ?? $t->branch->id,
            'customer_id'     => $t->customer->id,
            'cash_session_id' => null,
            'folio'           => 'F-'.(++self::$seq),
            'payment_type'    => $paymentType,
            'subtotal'        => $total,
            'tax_amount'      => '0.00',
            'discount_amount' => '0.00',
            'total'           => $total,
            'paid_amount'     => $paid,
            'payment_status'  => $paymentType === 'contado' ? 'pagada' : 'pendiente',
            'status'          => 'emitida',
            'issued_at'       => $issuedAtUtc,
            'issued_by'       => $t->owner->id,
        ])->saveQuietly();

        return $inv;
    }

    private function receivable(object $t, Invoice $invoice, string $total, string $paid, string $status = 'pendiente'): AccountReceivable
    {
        $ar = new AccountReceivable();
        $ar->forceFill([
            'business_id'  => $t->business->id,
            'customer_id'  => $t->customer->id,
            'invoice_id'   => $invoice->id,
            'total_amount' => $total,
            'paid_amount'  => $paid,
            'due_date'     => now()->addDays(30)->toDateString(),
            'status'       => $status,
        ])->save();

        return $ar;
    }

    /** Abono INMUTABLE (receivable_payments append-only) con su asiento fiscal 1:1 (invoice_payments). */
    private function payReceivable(object $t, AccountReceivable $ar, string $amount, string $paidAtUtc): void
    {
        $ipId = DB::table('invoice_payments')->insertGetId([
            'business_id'     => $t->business->id,
            'invoice_id'      => $ar->invoice_id,
            'cash_session_id' => null,
            'user_id'         => $t->owner->id,
            'payment_method'  => 'transferencia',
            'amount'          => $amount,
            'reference'       => 'REF-'.(++self::$seq),
            'paid_at'         => $paidAtUtc,
            'created_at'      => now(),
        ]);

        DB::table('receivable_payments')->insert([
            'business_id'            => $t->business->id,
            'accounts_receivable_id' => $ar->id,
            'invoice_payment_id'     => $ipId,
            'cash_session_id'        => null,
            'user_id'                => $t->owner->id,
            'payment_method'         => 'transferencia',
            'amount'                 => $amount,
            'reference'              => 'REF-'.self::$seq,
            'paid_at'                => $paidAtUtc,
            'created_at'             => now(),
        ]);
    }

    /** Reducción de CxC por nota de crédito (sales_return + credit_note + credit_note_resolution). */
    private function reduceCxc(object $t, Invoice $inv, string $amount, string $issuedAtUtc): void
    {
        $returnId = DB::table('sales_returns')->insertGetId([
            'business_id' => $t->business->id, 'branch_id' => $t->branch->id, 'invoice_id' => $inv->id,
            'customer_id' => $t->customer->id, 'user_id' => $t->owner->id, 'code' => 'DV-'.(++self::$seq),
            'status' => 'registrada', 'total_returned' => $amount, 'returned_at' => $issuedAtUtc,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cnId = DB::table('credit_notes')->insertGetId([
            'business_id' => $t->business->id, 'invoice_id' => $inv->id, 'sales_return_id' => $returnId,
            'customer_id' => $t->customer->id, 'cash_session_id' => null, 'issued_by' => $t->owner->id,
            'folio' => 'NC-'.self::$seq, 'resolution_type' => 'reduccion_cxc', 'total_amount' => $amount,
            'tax_amount' => '0.00', 'status' => 'emitida', 'issued_at' => $issuedAtUtc,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('credit_note_resolutions')->insert([
            'business_id' => $t->business->id, 'credit_note_id' => $cnId, 'cash_session_id' => null,
            'resolution_type' => 'reduccion_cxc', 'amount' => $amount, 'created_at' => now(),
        ]);
    }

    /** Anula una factura fijando voided_at (revierte la CxC como en revertirPorAnulacion). */
    private function voidInvoice(object $t, Invoice $inv, string $voidedAtUtc): void
    {
        DB::table('invoices')->where('id', $inv->id)->update([
            'status' => 'anulada', 'voided_by' => $t->owner->id, 'voided_at' => $voidedAtUtc,
            'void_reason' => 'prueba',
        ]);
        // Reversión de la CxC (total = pagado ⇒ balance 0), como el dominio; KPI usa i.total inmutable.
        DB::table('accounts_receivables')->where('invoice_id', $inv->id)
            ->update(['total_amount' => DB::raw('paid_amount'), 'status' => 'pagada']);
    }

    /** Sucursal adicional con su bodega y su operador (para pruebas por sucursal). */
    private function addBranch(object $t, string $name): object
    {
        $branch = new Branch();
        $branch->forceFill([
            'business_id' => $t->business->id, 'name' => $name, 'address' => 'Dir. '.$name,
            'opened_at' => now()->toDateString(), 'is_active' => true,
        ])->saveQuietly();

        $warehouse = new Warehouse();
        $warehouse->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $branch->id, 'name' => 'Bodega '.$name,
            'is_default' => true, 'is_active' => true,
        ])->save();

        $operator = $this->makeUser($t->business, RoleName::Operator, $branch);

        return (object) compact('branch', 'warehouse', 'operator');
    }

    private function snap(object $t, string $code, ?int $branchId, string $periodType = 'mensual', string $start = '2026-06-01'): ?KpiSnapshot
    {
        return KpiSnapshot::withoutGlobalScopes()->where('business_id', $t->business->id)
            ->where('kpi_code', $code)->where('period_type', $periodType)->whereDate('period_start', $start)
            ->when($branchId === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $branchId))
            ->first();
    }

    private function faltanteRule(object $t): AnomalyRule
    {
        return AnomalyRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)
            ->where('code', 'faltante_inventario')
            ->firstOrFail();
    }

    private function anomaly(object $t, string $detectedAtUtc, string $difference): Anomaly
    {
        $a = new Anomaly();
        $a->forceFill([
            'business_id'     => $t->business->id,
            'anomaly_rule_id' => $this->faltanteRule($t)->id,
            'branch_id'       => $t->branch->id,
            'severity'        => 'critica',
            'status'          => 'detectada',
            'difference'      => $difference,
            'source_type'     => 'physical_counts',
            'source_id'       => ++self::$seq,
            'detected_at'     => $detectedAtUtc,
        ])->save();

        return $a;
    }

    private function physicalCount(object $t, string $countedAtUtc, string $system, string $counted, ?int $warehouseId = null): PhysicalCount
    {
        $pc = new PhysicalCount();
        $pc->forceFill([
            'business_id'      => $t->business->id,
            'product_id'       => $this->makeProduct($t)->id,
            'warehouse_id'     => $warehouseId ?? $t->warehouse->id,
            'user_id'          => $t->owner->id,
            'system_quantity'  => $system,
            'counted_quantity' => $counted,
            'status'           => 'abierto',
            'counted_at'       => $countedAtUtc,
        ])->save();

        return $pc;
    }

    private function auditLog(object $t, int $userId, string $createdAtUtc): void
    {
        DB::table('audit_logs')->insert([
            'business_id'    => $t->business->id,
            'user_id'        => $userId,
            'action'         => 'test.action',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id'   => $userId,
            'created_at'     => $createdAtUtc,
        ]);
    }

    private function reconRun(object $t, string $startedAtUtc, string $status): void
    {
        DB::table('reconciliation_runs')->insert([
            'business_id' => $t->business->id,
            'run_type'    => 'programada',
            'scope'       => 'integral',
            'status'      => $status,
            'started_at'  => $startedAtUtc,
        ]);
    }

    private function storeGoal(object $t, array $override = []): TestResponse
    {
        return $this->asUser($t->owner)->postJson('/api/v1/business-goals', array_merge([
            'kpi_code'     => 'kpi_05',
            'period_type'  => 'mensual',
            'period_start' => '2026-06-01',
            'period_end'   => '2026-06-30',
            'target_value' => '1000.00',
        ], $override));
    }

    private function recalc(object $t, User $actor, string $period = 'mensual', ?string $ref = '2026-06-15'): TestResponse
    {
        $payload = ['period_type' => $period];
        if ($ref !== null) {
            $payload['reference_date'] = $ref;
        }

        return $this->asUser($actor)->postJson('/api/v1/kpi-snapshots/recalculate', $payload);
    }

    /** @param array<int, array<string, mixed>> $data */
    private function pluckKpi(array $data, string $code): ?array
    {
        foreach ($data as $row) {
            if ($row['kpi_code'] === $code) {
                return $row;
            }
        }

        return null;
    }

    // ============================ Metas de negocio ============================

    public function test_registro_canonico_es_coherente(): void
    {
        $kpis = (array) config('kpis');

        foreach (['kpi_01', 'kpi_02', 'kpi_03', 'kpi_04', 'kpi_05', 'kpi_06', 'kpi_07', 'kpi_08'] as $code) {
            $this->assertArrayHasKey($code, $kpis, "Falta {$code} en el registro canónico.");
        }

        $this->assertFalse($kpis['kpi_06']['goalable'], 'KPI-06 (agregador) no debe ser goalable.');
        $this->assertSame('down', $kpis['kpi_03']['direction'], 'KPI-03 (faltantes) debe ser de dirección a la baja.');

        // Todo código de meta admitida existe en el registro.
        foreach (\App\Enums\BusinessGoalKpiCode::values() as $code) {
            $this->assertArrayHasKey($code, $kpis, "El código de meta {$code} no existe en el registro.");
            $this->assertTrue($kpis[$code]['goalable'], "El código de meta {$code} debe ser goalable en el registro.");
        }
    }

    public function test_rol01_crea_meta_y_created_by_sale_de_la_sesion(): void
    {
        $t = $this->seedTenant('a');

        $res = $this->storeGoal($t)->assertStatus(201);
        $res->assertJsonPath('data.kpi_code', 'kpi_05');
        $res->assertJsonPath('data.created_by', $t->owner->id);
        $this->assertNull($res->json('data.branch_id'));
    }

    public function test_rol02_no_puede_crear_meta_pero_si_listar(): void
    {
        $t = $this->seedTenant('a');

        $this->asUser($t->admin)->postJson('/api/v1/business-goals', [
            'kpi_code' => 'kpi_05', 'period_type' => 'mensual',
            'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'target_value' => '10.00',
        ])->assertStatus(403);

        $this->asUser($t->admin)->getJson('/api/v1/business-goals')->assertOk();
    }

    public function test_operador_no_puede_listar_metas(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->operator)->getJson('/api/v1/business-goals')->assertStatus(403);
    }

    public function test_meta_global_y_por_sucursal_coexisten(): void
    {
        $t = $this->seedTenant('a');

        $this->storeGoal($t)->assertStatus(201);                                   // global
        $this->storeGoal($t, ['branch_id' => $t->branch->id])->assertStatus(201);  // por sucursal

        $this->assertSame(2, BusinessGoal::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_meta_duplicada_devuelve_422_controlado(): void
    {
        $t = $this->seedTenant('a');
        $this->storeGoal($t)->assertStatus(201);
        $this->storeGoal($t)->assertStatus(422); // GoalConflictException.
    }

    public function test_sucursal_de_otro_negocio_se_rechaza_sin_filtrar(): void
    {
        $t = $this->seedTenant('a');
        $other = $this->seedTenant('b');

        $this->storeGoal($t, ['branch_id' => $other->branch->id])->assertStatus(422);
    }

    public function test_solo_indicadores_goalable(): void
    {
        $t = $this->seedTenant('a');
        $this->storeGoal($t, ['kpi_code' => 'kpi_01'])->assertStatus(422); // no goalable.
    }

    public function test_meta_exige_valor_positivo_y_periodo_coherente(): void
    {
        $t = $this->seedTenant('a');
        $this->storeGoal($t, ['target_value' => '0'])->assertStatus(422);
        $this->storeGoal($t, ['period_end' => '2026-05-31'])->assertStatus(422);
    }

    public function test_actualizar_meta_no_cambia_identidad(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->storeGoal($t)->json('data.id');

        // Solo target_value y period_end son mutables.
        $res = $this->asUser($t->owner)->putJson("/api/v1/business-goals/{$id}", [
            'target_value' => '2000.00', 'period_end' => '2026-07-31',
        ])->assertOk();

        $res->assertJsonPath('data.target_value', '2000.00');
        $res->assertJsonPath('data.kpi_code', 'kpi_05');
    }

    public function test_eliminar_meta_rol01(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->storeGoal($t)->json('data.id');
        $this->asUser($t->owner)->deleteJson("/api/v1/business-goals/{$id}")->assertStatus(204);
        $this->assertNull(BusinessGoal::withoutGlobalScopes()->find($id));
    }

    // ============================ KPIs / recálculo ============================

    public function test_recalculo_calcula_los_ocho_kpis(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');

        $data = $this->recalc($t, $t->owner)->assertOk()->json('data');
        $codes = array_column($data, 'kpi_code');

        foreach (['kpi_01', 'kpi_02', 'kpi_03', 'kpi_04', 'kpi_05', 'kpi_06', 'kpi_07', 'kpi_08'] as $c) {
            $this->assertContains($c, $codes, "Falta {$c} en el recálculo.");
        }
    }

    public function test_kpi05_ventas_y_ticket_promedio_verificables(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-10 12:00:00', '100.00', 'contado');
        $this->invoice($t, '2026-06-20 12:00:00', '300.00', 'contado');

        $data = $this->recalc($t, $t->owner)->assertOk()->json('data');

        $this->assertSame('400.0000', $this->pluckKpi($data, 'kpi_05')['value']);
        $this->assertSame(2, $this->pluckKpi($data, 'kpi_05')['metadata']['invoice_count']);
        $this->assertSame('200.0000', $this->pluckKpi($data, 'ticket_promedio')['value']);
    }

    public function test_kpi08_recuperacion_cartera_verificable(): void
    {
        $t = $this->seedTenant('a');
        $inv = $this->invoice($t, '2026-06-15 12:00:00', '200.00', 'credito', '0.00');
        $ar = $this->receivable($t, $inv, '200.00', '0.00');
        $this->payReceivable($t, $ar, '50.00', '2026-06-20 12:00:00'); // abono inmutable en junio.

        $data = $this->recalc($t, $t->owner)->assertOk()->json('data');
        $kpi08 = $this->pluckKpi($data, 'kpi_08');

        $this->assertSame('25.0000', $kpi08['value']); // 50/200 * 100
        $this->assertSame('200.0000', $kpi08['metadata']['emitida']); // escala estable 4.
        $this->assertSame('50.0000', $kpi08['metadata']['recuperada']);
    }

    public function test_kpi08_esta_acotado_por_periodo(): void
    {
        $t = $this->seedTenant('a');
        // Cohorte de JUNIO.
        $junio = $this->invoice($t, '2026-06-15 12:00:00', '200.00', 'credito', '0.00');
        $this->receivable($t, $junio, '200.00', '100.00');
        // Cohorte de JULIO (no debe contaminar junio).
        $julio = $this->invoice($t, '2026-07-15 12:00:00', '500.00', 'credito', '0.00');
        $this->receivable($t, $julio, '500.00', '0.00');

        $junioData = $this->recalc($t, $t->owner, 'mensual', '2026-06-15')->assertOk()->json('data');
        $this->assertSame('200.0000', $this->pluckKpi($junioData, 'kpi_08')['metadata']['emitida']);

        $julioData = $this->recalc($t, $t->owner, 'mensual', '2026-07-15')->assertOk()->json('data');
        $this->assertSame('500.0000', $this->pluckKpi($julioData, 'kpi_08')['metadata']['emitida']);
    }

    public function test_kpi03_faltantes_direccion_a_la_baja(): void
    {
        $t = $this->seedTenant('a');
        $this->anomaly($t, '2026-06-15 12:00:00', '-30.00'); // faltante.

        // Meta a la baja: tope 100, real 30 => logro = 100/30*100 (>100, buen desempeño).
        $this->asUser($t->owner)->postJson('/api/v1/business-goals', [
            'kpi_code' => 'kpi_03', 'period_type' => 'mensual',
            'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'target_value' => '100.00',
        ])->assertStatus(201);

        $data = $this->recalc($t, $t->owner)->assertOk()->json('data');
        $kpi03 = $this->pluckKpi($data, 'kpi_03');

        $this->assertSame('30.0000', $kpi03['value']); // |−30|
        // Dirección 'down': target/valor*100 = 100/30*100 ≈ 333.33 (no valor/target).
        $this->assertSame('333.33', $kpi03['achievement_pct']);
    }

    public function test_kpi06_es_promedio_de_logros_y_se_calcula_al_final(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');

        // Meta kpi_05 = 100 (valor real) => logro exacto 100.00. Único KPI con meta.
        $this->storeGoal($t, ['kpi_code' => 'kpi_05', 'target_value' => '100.00']);

        $data = $this->recalc($t, $t->owner)->assertOk()->json('data');

        $this->assertSame('100.00', $this->pluckKpi($data, 'kpi_05')['achievement_pct']);
        $this->assertSame('100.0000', $this->pluckKpi($data, 'kpi_06')['value']);
        $this->assertSame(1, $this->pluckKpi($data, 'kpi_06')['metadata']['sample']);
    }

    public function test_corte_de_periodo_respeta_timezone_del_negocio(): void
    {
        $t = $this->seedTenant('a');
        // Managua = UTC-6. Día local 2026-06-15 => [06:00Z, +1d 06:00Z).
        $this->invoice($t, '2026-06-15 05:00:00', '111.00', 'contado'); // 2026-06-14 23:00 local → FUERA.
        $this->invoice($t, '2026-06-15 07:00:00', '222.00', 'contado'); // 2026-06-15 01:00 local → DENTRO.

        $data = $this->recalc($t, $t->owner, 'diario', '2026-06-15')->assertOk()->json('data');

        $this->assertSame('222.0000', $this->pluckKpi($data, 'kpi_05')['value']);
        $this->assertSame(1, $this->pluckKpi($data, 'kpi_05')['metadata']['invoice_count']);
    }

    public function test_periodo_vacio_es_determinista_sin_errores(): void
    {
        $t = $this->seedTenant('a'); // sin datos operativos.

        $data = $this->recalc($t, $t->owner)->assertOk()->json('data');

        $this->assertSame('0.0000', $this->pluckKpi($data, 'kpi_05')['value']);
        $this->assertSame('100.0000', $this->pluckKpi($data, 'kpi_02')['value']); // sin conteos → 100%.
        $this->assertSame('0.0000', $this->pluckKpi($data, 'kpi_08')['value']);   // sin cartera → 0, no NaN.
    }

    public function test_recalculo_es_idempotente(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');

        $this->recalc($t, $t->owner)->assertOk();
        $this->recalc($t, $t->owner)->assertOk();

        // Sin duplicados: una sola instantánea GLOBAL por (negocio, kpi, período, inicio).
        $rows = KpiSnapshot::withoutGlobalScopes()
            ->where('business_id', $t->business->id)
            ->where('kpi_code', 'kpi_05')->where('period_type', 'mensual')
            ->whereNull('branch_id')
            ->whereDate('period_start', '2026-06-01')->count();
        $this->assertSame(1, $rows);
    }

    public function test_meta_congelada_no_cambia_snapshot_retroactivo(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');
        $this->storeGoal($t, ['kpi_code' => 'kpi_05', 'target_value' => '100.00']);

        $this->recalc($t, $t->owner)->assertOk();
        $frozen = KpiSnapshot::withoutGlobalScopes()->where('business_id', $t->business->id)
            ->where('kpi_code', 'kpi_05')->whereNull('branch_id')->whereDate('period_start', '2026-06-01')->firstOrFail();
        $this->assertSame('100.00', (string) $frozen->target_value);

        // Cambiar la meta viva NO altera el snapshot ya calculado hasta recalcular.
        $id = BusinessGoal::withoutGlobalScopes()->where('business_id', $t->business->id)->value('id');
        $this->asUser($t->owner)->putJson("/api/v1/business-goals/{$id}", ['target_value' => '5000.00'])->assertOk();

        $frozen->refresh();
        $this->assertSame('100.00', (string) $frozen->target_value); // congelada.
    }

    public function test_solo_rol01_recalcula_y_ve_dashboard(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->admin)->postJson('/api/v1/kpi-snapshots/recalculate', ['period_type' => 'mensual'])->assertStatus(403);
        $this->asUser($t->admin)->getJson('/api/v1/dashboard/kpis')->assertStatus(403);
        $this->asUser($t->owner)->getJson('/api/v1/dashboard/kpis?period_type=mensual')->assertOk();
    }

    public function test_dashboard_sirve_solo_datos_del_negocio(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');
        $this->recalc($t, $t->owner, 'mensual', '2026-06-15')->assertOk();

        $data = $this->asUser($t->owner)->getJson('/api/v1/dashboard/kpis?period_type=mensual&period_start=2026-06-01')
            ->assertOk()->json('data');

        $this->assertNotEmpty($data);
        foreach ($data as $row) {
            $this->assertArrayHasKey('label', $row);
            $this->assertArrayHasKey('unit', $row);
            $this->assertArrayHasKey('family', $row);
        }
    }

    // ============================ Reportes ============================

    public function test_reportes_devuelven_estructura_comparable(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');

        foreach (['ventas', 'cartera', 'inventario', 'caja', 'consolidado'] as $type) {
            $res = $this->asUser($t->owner)->getJson("/api/v1/reports/{$type}?from=2026-06-01&to=2026-06-30")->assertOk();
            $res->assertJsonStructure(['data' => ['type', 'period', 'totals', 'comparisons', 'series', 'metadata']]);
            $res->assertJsonPath('data.type', $type);
        }
    }

    public function test_reporte_tipo_desconocido_404(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->owner)->getJson('/api/v1/reports/inexistente?from=2026-06-01&to=2026-06-30')->assertStatus(404);
    }

    public function test_reporte_ventas_compara_periodo_anterior(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '300.00', 'contado'); // período actual (junio)
        $this->invoice($t, '2026-05-15 12:00:00', '100.00', 'contado'); // período anterior (mayo)

        $res = $this->asUser($t->owner)->getJson('/api/v1/reports/ventas?from=2026-06-01&to=2026-06-30')->assertOk();

        $res->assertJsonPath('data.totals.total_sold', '300.00');
        $res->assertJsonPath('data.comparisons.previous_total', '100.00');
        $res->assertJsonPath('data.comparisons.delta', '200.00');
    }

    public function test_reporte_no_muta_datos_operativos(): void
    {
        $t = $this->seedTenant('a');
        $inv = $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');
        $before = Invoice::withoutGlobalScopes()->where('business_id', $t->business->id)->count();
        $snapsBefore = KpiSnapshot::withoutGlobalScopes()->where('business_id', $t->business->id)->count();

        $this->asUser($t->owner)->getJson('/api/v1/reports/consolidado?from=2026-06-01&to=2026-06-30')->assertOk();

        $this->assertSame($before, Invoice::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        $this->assertSame($snapsBefore, KpiSnapshot::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    // ============================ Definiciones de reporte ============================

    public function test_definicion_de_reporte_crud_y_user_de_la_sesion(): void
    {
        $t = $this->seedTenant('a');

        $res = $this->asUser($t->admin)->postJson('/api/v1/report-definitions', [
            'name' => 'Ventas junio', 'report_type' => 'ventas',
            'filters' => ['period_type' => 'mensual', 'from' => '2026-06-01', 'to' => '2026-06-30'],
        ])->assertStatus(201);
        $res->assertJsonPath('data.user_id', $t->admin->id);
        $id = $res->json('data.id');

        $this->asUser($t->admin)->putJson("/api/v1/report-definitions/{$id}", ['name' => 'Ventas Q2'])->assertOk()
            ->assertJsonPath('data.name', 'Ventas Q2');

        $this->asUser($t->admin)->deleteJson("/api/v1/report-definitions/{$id}")->assertStatus(204);
    }

    public function test_definicion_rechaza_tipo_fuera_de_allowlist(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->admin)->postJson('/api/v1/report-definitions', [
            'name' => 'X', 'report_type' => 'malicioso',
        ])->assertStatus(422);
    }

    public function test_is_scheduled_se_persiste_inerte(): void
    {
        $t = $this->seedTenant('a');
        $res = $this->asUser($t->admin)->postJson('/api/v1/report-definitions', [
            'name' => 'Programado', 'report_type' => 'caja',
            'is_scheduled' => true, 'schedule_cron' => '0 6 * * *',
        ])->assertStatus(201);
        $res->assertJsonPath('data.is_scheduled', true); // Persistido, sin motor de envío (Fase 2).
    }

    // ============================ Comando / scheduler ============================

    public function test_comando_recorre_tenants_sin_sesion_y_es_idempotente(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');

        // Sin actingAs: el comando no depende de la sesión.
        $this->assertSame(0, Artisan::call('kpi:snapshot', ['--period' => 'mensual']));
        $this->assertSame(0, Artisan::call('kpi:snapshot', ['--period' => 'mensual']));

        // Idempotente: sin duplicados de la instantánea GLOBAL del período vigente del negocio.
        $rows = KpiSnapshot::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('kpi_code', 'kpi_05')
            ->where('period_type', 'mensual')->whereNull('branch_id')->count();
        $this->assertSame(1, $rows);
    }

    public function test_comando_omite_negocios_suspendidos(): void
    {
        $t = $this->seedTenant('a');
        $t->business->update(['status' => 'suspended']);

        Artisan::call('kpi:snapshot', ['--period' => 'mensual']);

        $this->assertSame(0, KpiSnapshot::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_comando_rechaza_periodo_invalido(): void
    {
        $this->assertNotSame(0, Artisan::call('kpi:snapshot', ['--period' => 'quincenal']));
    }

    // ============================ Microcierre 1: unicidad con period_type ============================

    public function test_uniq_business_goal_incluye_period_type_en_orden(): void
    {
        $db = (string) config('database.connections.mysql.database');
        $cols = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',
            [$db, 'business_goals', 'uniq_business_goal'],
        );
        $names = array_map(static fn ($r) => $r->COLUMN_NAME, $cols);

        $this->assertSame(['business_id', 'branch_key', 'kpi_code', 'period_type', 'period_start'], $names);
    }

    public function test_esquema_uniq_proviene_de_migraciones_versionadas(): void
    {
        // Evidencia de REPRODUCIBILIDAD: el índice proviene del código versionado, no del estado
        // particular de esta base. La migración de creación define el UNIQUE de las cinco columnas...
        $create = (string) file_get_contents(
            database_path('migrations/2026_07_18_193313_create_business_goals_table.php')
        );
        $this->assertMatchesRegularExpression(
            "/unique\(\s*\[\s*'business_id',\s*'branch_key',\s*'kpi_code',\s*'period_type',\s*'period_start'\s*\]/",
            $create,
            'La migración de creación debe definir el UNIQUE con las cinco columnas.'
        );

        // ...y existe la migración correctiva idempotente que converge bases legadas de 4 columnas.
        $this->assertFileExists(
            database_path('migrations/2026_09_25_000003_align_uniq_business_goal_period_type.php')
        );
    }

    public function test_meta_diaria_y_mensual_del_mismo_kpi_coexisten(): void
    {
        $t = $this->seedTenant('a');

        // Mismo KPI, misma sucursal (global), mismo period_start; distinto period_type ⇒ coexisten.
        $this->storeGoal($t, ['period_type' => 'mensual', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30'])->assertStatus(201);
        $this->storeGoal($t, ['period_type' => 'diario', 'period_start' => '2026-06-01', 'period_end' => '2026-06-01'])->assertStatus(201);

        $this->assertSame(2, BusinessGoal::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_unicidad_por_period_type_para_global_y_sucursal(): void
    {
        $t = $this->seedTenant('a');

        // GLOBAL: diaria y mensual coexisten; duplicar la mensual global ⇒ 422.
        $this->storeGoal($t, ['period_type' => 'diario', 'period_start' => '2026-06-01', 'period_end' => '2026-06-01'])->assertStatus(201);
        $this->storeGoal($t, ['period_type' => 'mensual'])->assertStatus(201);
        $this->storeGoal($t, ['period_type' => 'mensual'])->assertStatus(422);

        // SUCURSAL: diaria y mensual coexisten; duplicar la mensual de sucursal ⇒ 422.
        $b = ['branch_id' => $t->branch->id];
        $this->storeGoal($t, $b + ['period_type' => 'diario', 'period_start' => '2026-06-01', 'period_end' => '2026-06-01'])->assertStatus(201);
        $this->storeGoal($t, $b + ['period_type' => 'mensual'])->assertStatus(201);
        $this->storeGoal($t, $b + ['period_type' => 'mensual'])->assertStatus(422);
    }

    public function test_colision_concurrente_uniq_rechaza_insert_directo(): void
    {
        $t = $this->seedTenant('a');
        $this->storeGoal($t, ['period_type' => 'mensual'])->assertStatus(201);

        // Simula al perdedor de una carrera: un INSERT directo que evade el pre-chequeo del Service.
        // El candado uniq_business_goal (branch_key colapsa el NULL) es la barrera real → 1062.
        $threw1062 = false;
        try {
            DB::table('business_goals')->insert([
                'business_id'  => $t->business->id,
                'branch_id'    => null,
                'kpi_code'     => 'kpi_05',
                'period_type'  => 'mensual',
                'period_start' => '2026-06-01',
                'period_end'   => '2026-06-30',
                'target_value' => '1000.00',
                'created_by'   => $t->owner->id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (QueryException $e) {
            $threw1062 = (int) ($e->errorInfo[1] ?? 0) === 1062;
        }

        $this->assertTrue($threw1062, 'El índice UNIQUE debe rechazar el duplicado concurrente (1062).');
        $this->assertSame(1, BusinessGoal::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    // ============================ Microcierre 2: snapshots por sucursal ============================

    public function test_recalculo_genera_snapshot_global_y_por_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');

        $this->recalc($t, $t->owner)->assertOk();

        $this->assertNotNull($this->snap($t, 'kpi_05', null), 'Falta el snapshot GLOBAL.');
        $this->assertNotNull($this->snap($t, 'kpi_05', $t->branch->id), 'Falta el snapshot por SUCURSAL.');
    }

    public function test_kpi05_por_sucursal_aisla_facturas(): void
    {
        $t = $this->seedTenant('a');
        $b2 = $this->addBranch($t, 'S2');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');                        // principal
        $this->invoice($t, '2026-06-16 12:00:00', '300.00', 'contado', null, $b2->branch->id); // S2

        $this->recalc($t, $t->owner)->assertOk();

        $this->assertSame('100.0000', $this->snap($t, 'kpi_05', $t->branch->id)->value);
        $this->assertSame('300.0000', $this->snap($t, 'kpi_05', $b2->branch->id)->value);
        $this->assertSame('400.0000', $this->snap($t, 'kpi_05', null)->value); // global = suma.
    }

    public function test_meta_de_sucursal_solo_aplica_a_su_snapshot(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');
        $this->storeGoal($t, ['kpi_code' => 'kpi_05', 'target_value' => '100.00', 'branch_id' => $t->branch->id]);

        $this->recalc($t, $t->owner)->assertOk();

        $this->assertSame('100.00', (string) $this->snap($t, 'kpi_05', $t->branch->id)->target_value);
        $this->assertSame('100.00', (string) $this->snap($t, 'kpi_05', $t->branch->id)->achievement_pct);
        // La meta de sucursal NO contamina el snapshot global.
        $this->assertNull($this->snap($t, 'kpi_05', null)->target_value);
    }

    public function test_meta_global_no_contamina_snapshot_de_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'contado');
        $this->storeGoal($t, ['kpi_code' => 'kpi_05', 'target_value' => '100.00']); // global.

        $this->recalc($t, $t->owner)->assertOk();

        $this->assertSame('100.00', (string) $this->snap($t, 'kpi_05', null)->target_value);
        $this->assertNull($this->snap($t, 'kpi_05', $t->branch->id)->target_value);
    }

    public function test_kpi02_por_sucursal_deriva_por_bodega(): void
    {
        $t = $this->seedTenant('a');
        $b2 = $this->addBranch($t, 'S2');
        $this->physicalCount($t, '2026-06-15 12:00:00', '100', '90', $t->warehouse->id);   // dev 10 → 90%
        $this->physicalCount($t, '2026-06-15 12:00:00', '100', '100', $b2->warehouse->id); // dev 0 → 100%

        $this->recalc($t, $t->owner)->assertOk();

        $this->assertSame('90.0000', $this->snap($t, 'kpi_02', $t->branch->id)->value);
        $this->assertSame('100.0000', $this->snap($t, 'kpi_02', $b2->branch->id)->value);
    }

    public function test_kpi04_por_sucursal_usa_usuarios_de_la_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $this->auditLog($t, $t->operator->id, '2026-06-15 12:00:00'); // operador de la sucursal principal.

        $this->recalc($t, $t->owner)->assertOk();

        $branchSnap = $this->snap($t, 'kpi_04', $t->branch->id);
        $this->assertSame(1, $branchSnap->metadata['enabled_users']); // solo el operador de esa sucursal.
        $this->assertSame(1, $branchSnap->metadata['active_users']);
        $this->assertSame('100.0000', $branchSnap->value);
    }

    public function test_kpi08_por_sucursal_deriva_desde_la_factura(): void
    {
        $t = $this->seedTenant('a');
        $b2 = $this->addBranch($t, 'S2');
        $ar1 = $this->receivable($t, $this->invoice($t, '2026-06-15 12:00:00', '200.00', 'credito', '0.00'), '200.00', '0.00');
        $this->payReceivable($t, $ar1, '50.00', '2026-06-20 12:00:00');
        $ar2 = $this->receivable($t, $this->invoice($t, '2026-06-16 12:00:00', '400.00', 'credito', '0.00', $b2->branch->id), '400.00', '0.00');
        $this->payReceivable($t, $ar2, '100.00', '2026-06-21 12:00:00');

        $this->recalc($t, $t->owner)->assertOk();

        $this->assertSame('200.0000', $this->snap($t, 'kpi_08', $t->branch->id)->metadata['emitida']);
        $this->assertSame('50.0000', $this->snap($t, 'kpi_08', $t->branch->id)->metadata['recuperada']);
        $this->assertSame('400.0000', $this->snap($t, 'kpi_08', $b2->branch->id)->metadata['emitida']);
        $this->assertSame('100.0000', $this->snap($t, 'kpi_08', $b2->branch->id)->metadata['recuperada']);
    }

    public function test_no_hay_mezcla_entre_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $this->invoice($a, '2026-06-15 12:00:00', '100.00', 'contado');
        $this->invoice($b, '2026-06-15 12:00:00', '999.00', 'contado');

        $this->recalc($a, $a->owner)->assertOk();

        $this->assertSame('100.0000', $this->snap($a, 'kpi_05', null)->value); // no incluye al negocio B.
    }

    // ============================ Microcierre 3: semántica histórica KPI-08 ============================

    public function test_kpi08_historico_es_inmutable_ante_abono_posterior(): void
    {
        $t = $this->seedTenant('a');
        $ar = $this->receivable($t, $this->invoice($t, '2026-06-15 12:00:00', '200.00', 'credito', '0.00'), '200.00', '0.00');
        $this->payReceivable($t, $ar, '50.00', '2026-06-20 12:00:00'); // abono en JUNIO.
        $this->payReceivable($t, $ar, '30.00', '2026-07-10 12:00:00'); // abono en JULIO.

        // Recalcular JUNIO tras el abono de julio conserva exactamente el cierre de junio.
        $junio = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->assertOk()->json('data'), 'kpi_08');
        $this->assertSame('200.0000', $junio['metadata']['emitida']);
        $this->assertSame('50.0000', $junio['metadata']['recuperada']);   // NO 80: el abono de julio no cuenta.
        $this->assertSame('150.0000', $junio['metadata']['pendiente']);
        $this->assertSame('25.0000', $junio['value']);

        // Recalcular otra vez junio: idéntico (reproducible).
        $junio2 = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->assertOk()->json('data'), 'kpi_08');
        $this->assertSame('50.0000', $junio2['metadata']['recuperada']);

        // Julio NO duplica la cartera emitida de junio (cohorte por período).
        $julio = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-07-15')->assertOk()->json('data'), 'kpi_08');
        $this->assertSame('0.0000', $julio['metadata']['emitida']);
    }

    public function test_kpi08_vencida_es_saldo_pendiente_con_due_date_vencida_al_cierre(): void
    {
        $t = $this->seedTenant('a');
        // Factura de junio con CxC ya vencida al cierre de junio (due_date dentro del período) y saldo pendiente.
        $inv = $this->invoice($t, '2026-06-05 12:00:00', '200.00', 'credito', '0.00');
        $ar = new AccountReceivable();
        $ar->forceFill([
            'business_id' => $t->business->id, 'customer_id' => $t->customer->id, 'invoice_id' => $inv->id,
            'total_amount' => '200.00', 'paid_amount' => '0.00', 'due_date' => '2026-06-20', 'status' => 'vencida',
        ])->save();
        $this->payReceivable($t, $ar, '50.00', '2026-06-15 12:00:00');

        $kpi08 = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->assertOk()->json('data'), 'kpi_08');

        $this->assertSame('150.0000', $kpi08['metadata']['vencida']); // 200 − 50 recuperado al cierre.
    }

    public function test_kpi08_contabiliza_todos_los_eventos_de_cartera(): void
    {
        $t = $this->seedTenant('a');

        // (1) Factura a crédito de 100 emitida en junio con PAGO INICIAL de 20.
        $inv = $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'credito', '20.00');
        $ar = $this->receivable($t, $inv, '100.00', '20.00');
        $this->payReceivable($t, $ar, '20.00', '2026-06-15 12:00:00'); // inicial: paid_at == issued_at.

        $k = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->json('data'), 'kpi_08');
        $this->assertSame('100.0000', $k['metadata']['emitida']);
        $this->assertSame('20.0000', $k['metadata']['pagos_iniciales']);
        $this->assertSame('0.0000', $k['metadata']['abonos_posteriores']);
        $this->assertSame('20.0000', $k['metadata']['recuperada']);
        $this->assertSame('80.0000', $k['metadata']['pendiente']);

        // (2) Abono POSTERIOR de 30 en junio (el inicial no se duplica).
        $this->payReceivable($t, $ar, '30.00', '2026-06-20 12:00:00');
        $k = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->json('data'), 'kpi_08');
        $this->assertSame('20.0000', $k['metadata']['pagos_iniciales']);
        $this->assertSame('30.0000', $k['metadata']['abonos_posteriores']);
        $this->assertSame('50.0000', $k['metadata']['recuperada']);
        $this->assertSame('50.0000', $k['metadata']['pendiente']);

        // (3) Otro abono de 10 en JULIO: recalcular junio conserva 50; julio no duplica la emitida de junio.
        $this->payReceivable($t, $ar, '10.00', '2026-07-10 12:00:00');
        $junio = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->json('data'), 'kpi_08');
        $this->assertSame('50.0000', $junio['metadata']['recuperada']);
        $julio = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-07-15')->json('data'), 'kpi_08');
        $this->assertSame('0.0000', $julio['metadata']['emitida']);

        // (4) Reducción de CxC por NC de 15 emitida en junio: recuperada sigue 50; reducciones=15; pendiente=35.
        $this->reduceCxc($t, $inv, '15.00', '2026-06-25 12:00:00');
        $k = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->json('data'), 'kpi_08');
        $this->assertSame('50.0000', $k['metadata']['recuperada']);   // la NC NO es dinero recuperado.
        $this->assertSame('15.0000', $k['metadata']['reducciones_cxc']);
        $this->assertSame('35.0000', $k['metadata']['pendiente']);

        // (5) Una NC emitida en JULIO no modifica el cierre de junio.
        $this->reduceCxc($t, $inv, '5.00', '2026-07-05 12:00:00');
        $k = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->json('data'), 'kpi_08');
        $this->assertSame('15.0000', $k['metadata']['reducciones_cxc']);
        $this->assertSame('35.0000', $k['metadata']['pendiente']);
    }

    public function test_kpi08_anulacion_posterior_no_reescribe_periodo_anterior(): void
    {
        $t = $this->seedTenant('a');
        $inv = $this->invoice($t, '2026-06-15 12:00:00', '100.00', 'credito', '0.00');
        $this->receivable($t, $inv, '100.00', '0.00');

        // Anulación en JULIO (posterior al cierre de junio).
        $this->voidInvoice($t, $inv, '2026-07-10 12:00:00');

        // Junio conserva la obligación original vigente al cierre (voided_at >= corte), no la reescribe.
        $k = $this->pluckKpi($this->recalc($t, $t->owner, 'mensual', '2026-06-15')->json('data'), 'kpi_08');
        $this->assertSame('100.0000', $k['metadata']['emitida']);
        $this->assertSame('100.0000', $k['metadata']['pendiente']);
    }

    // ============================ Microcierre 4: alcance de KPI-01 ============================

    public function test_kpi01_publica_componentes_y_pata_de_inventario_diferida(): void
    {
        $t = $this->seedTenant('a');
        $this->invoice($t, '2026-06-10 12:00:00', '100.00', 'contado');                                   // cobrado
        $this->receivable($t, $this->invoice($t, '2026-06-12 12:00:00', '200.00', 'credito', '0.00'), '200.00', '0.00'); // CxC generada

        $kpi01 = $this->pluckKpi($this->recalc($t, $t->owner)->assertOk()->json('data'), 'kpi_01');

        // Facturado 300 = contado 100 (cobrado) + crédito 200 (con CxC) ⇒ correspondencia 100%.
        $this->assertSame('100.0000', $kpi01['value']);
        $this->assertSame('facturado_vs_cobros_y_cxc', $kpi01['metadata']['measures']);
        $this->assertStringContainsString('diferido_fase2', $kpi01['metadata']['inventory_leg']);
        $this->assertSame('300.0000', $kpi01['metadata']['invoiced']);
        $this->assertSame('100.0000', $kpi01['metadata']['contado']);
        $this->assertSame('200.0000', $kpi01['metadata']['credito']);
        $this->assertNull($kpi01['target_value']); // índice de salud, sin meta.

        // El registro canónico también deja explícita la pata diferida.
        $this->assertSame('diferido_fase2', config('kpis.kpi_01.inventory_leg'));
    }

    // ============================ Aislamiento / esquema ============================

    public function test_aislamiento_entre_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $this->storeGoal($a)->assertStatus(201);

        // El negocio B no ve las metas de A.
        $data = $this->asUser($b->owner)->getJson('/api/v1/business-goals')->assertOk()->json('data');
        $this->assertCount(0, $data);
    }

    public function test_esquema_kpi_tiene_uniques_y_checks(): void
    {
        $db = (string) config('database.connections.mysql.database');

        foreach (['uniq_kpi_snapshot', 'uniq_business_goal'] as $index) {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0',
                [$db, $index],
            );
            $this->assertGreaterThan(0, (int) $row->c, "Falta el índice UNIQUE {$index}.");
        }

        foreach (['chk_snapshot_period', 'chk_goal_target_positive', 'chk_goal_period'] as $check) {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ? AND CONSTRAINT_TYPE = ? AND CONSTRAINT_NAME = ?',
                [$db, 'CHECK', $check],
            );
            $this->assertGreaterThan(0, (int) $row->c, "Falta el CHECK {$check}.");
        }

        $views = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN
             ('vw_kpi_ventas','vw_kpi_cartera','vw_kpi_exactitud_stock','vw_kpi_faltantes','vw_kpi_uso_sistema','vw_kpi_disponibilidad')",
            [$db],
        );
        $this->assertSame(6, (int) $views->c);
    }
}
