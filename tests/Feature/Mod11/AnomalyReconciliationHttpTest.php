<?php

declare(strict_types=1);

namespace Tests\Feature\Mod11;

use App\Enums\ReconciliationScope;
use App\Enums\RoleName;
use App\Models\Anomaly;
use App\Models\AnomalyEvent;
use App\Models\AnomalyRule;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\AccountReceivable;
use App\Models\PhysicalCount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReconciliationRun;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Anomaly\AnomalyService;
use App\Services\Anomaly\ReconciliationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-11 · Conciliación, Alertas y Gestión de Anomalías (HTTP e2e contra MySQL).
 *
 * Verifica el catálogo cerrado de 6 reglas, la parametrización ROL-01, la máquina de estados
 * con bitácora append-only, BR-01 (el causante no valida), la deduplicación respaldada por el
 * candado UNIQUE del motor, la liberación del candado, las conciliaciones manual/programada por
 * ámbito (solo lectura), el aislamiento multitenant y la integridad del esquema.
 */
final class AnomalyReconciliationHttpTest extends MysqlTestCase
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
        // El observer siembra las 6 reglas de anomalía + cliente genérico + secuencias.

        $branch    = $this->makeBranch($business, 'S1 '.$slug);
        $warehouse = $this->makeWarehouse($business, $branch, 'B1 '.$slug);

        $owner    = $this->makeUser($business, RoleName::Owner);
        $admin    = $this->makeUser($business, RoleName::Admin);
        $admin2   = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator, $branch);

        $category = new Category(['name' => 'Cat '.$slug]);
        $category->business_id = $business->id;
        $category->save();

        $unit = new UnitOfMeasure(['name' => 'Unidad', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        $register = new CashRegister();
        $register->forceFill([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Caja '.$slug,
            'is_active'   => true,
        ])->save();

        return (object) compact('business', 'owner', 'admin', 'admin2', 'operator', 'branch', 'warehouse', 'category', 'unit', 'register');
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

    /** Sesión de caja CERRADA con descuadre (difference = counted − expected, columna generada). */
    private function closedCashSession(object $t, User $openedBy, string $expected, string $counted): CashSession
    {
        $s = new CashSession();
        $s->forceFill([
            'business_id'      => $t->business->id,
            'cash_register_id' => $t->register->id,
            'opened_by'        => $openedBy->id,
            'closed_by'        => $openedBy->id,
            'status'           => 'cerrada',
            'opening_amount'   => '0.00',
            'expected_amount'  => $expected,
            'counted_amount'   => $counted,
            'opened_at'        => now()->subHours(2),
            'closed_at'        => now()->subHour(),
        ])->saveQuietly();

        return $s->refresh();
    }

    /** Conteo físico con faltante (difference = counted − system, columna generada). */
    private function physicalCount(object $t, User $user, string $system, string $counted): PhysicalCount
    {
        $pc = new PhysicalCount();
        $pc->forceFill([
            'business_id'      => $t->business->id,
            'product_id'       => $this->makeProduct($t)->id,
            'warehouse_id'     => $t->warehouse->id,
            'user_id'          => $user->id,
            'system_quantity'  => $system,
            'counted_quantity' => $counted,
            'status'           => 'abierto',
            'counted_at'       => now(),
        ])->save();

        return $pc;
    }

    /** Recepción de compra con match no conforme (discrepancia 3-way). */
    private function goodsReceipt(object $t, User $user, string $matchStatus = 'discrepancia'): GoodsReceipt
    {
        $supplier = new Supplier();
        $supplier->forceFill([
            'business_id' => $t->business->id,
            'name'        => 'Proveedor '.(++self::$seq),
            'status'      => 'aprobado',
            'is_active'   => true,
        ])->save();

        $po = new PurchaseOrder();
        $po->forceFill([
            'business_id'    => $t->business->id,
            'branch_id'      => $t->branch->id,
            'supplier_id'    => $supplier->id,
            'user_id'        => $user->id,
            'code'           => 'OC-'.self::$seq,
            'status'         => 'recibida',
            'expected_total' => '100.00',
            'ordered_at'     => now()->toDateString(),
        ])->save();

        $gr = new GoodsReceipt();
        $gr->forceFill([
            'business_id'      => $t->business->id,
            'purchase_order_id' => $po->id,
            'warehouse_id'     => $t->warehouse->id,
            'user_id'          => $user->id,
            'match_status'     => $matchStatus,
            'received_at'      => now(),
        ])->save();

        return $gr;
    }

    /** Cuenta por cobrar vencida (para el hook cuenta_vencida vía receivables:mark-overdue). */
    private function overdueReceivable(object $t): AccountReceivable
    {
        $customer = new Customer([
            'name'            => 'Cliente '.(++self::$seq),
            'document_type'   => 'cedula',
            'document_number' => 'DOC-'.self::$seq,
            'credit_limit'    => '100000.00',
        ]);
        $customer->business_id = $t->business->id;
        $customer->save();

        $invoice = new Invoice();
        $invoice->forceFill([
            'business_id'     => $t->business->id,
            'branch_id'       => $t->branch->id,
            'customer_id'     => $customer->id,
            'cash_session_id' => null,
            'folio'           => 'F-'.self::$seq,
            'payment_type'    => 'credito',
            'subtotal'        => '100.00',
            'tax_amount'      => '0.00',
            'discount_amount' => '0.00',
            'total'           => '100.00',
            'paid_amount'     => '0.00',
            'payment_status'  => 'pendiente',
            'status'          => 'emitida',
            'issued_at'       => now()->subDays(40),
            'issued_by'       => $t->operator->id,
        ])->saveQuietly();

        $ar = new AccountReceivable();
        $ar->forceFill([
            'business_id'  => $t->business->id,
            'customer_id'  => $customer->id,
            'invoice_id'   => $invoice->id,
            'total_amount' => '100.00',
            'paid_amount'  => '0.00',
            'due_date'     => now()->subDays(5)->toDateString(),
            'status'       => 'pendiente',
        ])->save();

        return $ar;
    }

    private function runReconciliation(object $t, string $scope, User $actor, ?int $branchId = null): TestResponse
    {
        $payload = ['scope' => $scope];
        if ($branchId !== null) {
            $payload['branch_id'] = $branchId;
        }

        return $this->asUser($actor)->postJson('/api/v1/reconciliation-runs', $payload);
    }

    private function anomalyBySource(object $t, string $sourceType): Anomaly
    {
        return Anomaly::withoutGlobalScopes()
            ->where('business_id', $t->business->id)
            ->where('source_type', $sourceType)
            ->latest('id')
            ->firstOrFail();
    }

    // =======================================================================
    // Reglas de anomalía
    // =======================================================================

    public function test_seis_reglas_cerradas_sembradas_idempotentemente(): void
    {
        $t = $this->seedTenant('a');

        $codes = AnomalyRule::withoutGlobalScopes()->where('business_id', $t->business->id)
            ->pluck('code')->map(static fn ($c) => $c->value)->sort()->values()->all();
        $this->assertCount(6, $codes);
        $this->assertSame(
            ['cuenta_vencida', 'descuadre_caja', 'discrepancia_3way', 'faltante_inventario', 'omision_registro', 'venta_sin_sesion'],
            $codes
        );

        // Idempotencia: reejecutar el seed del observer no duplica (uniq_anomaly_rule_code).
        app(\App\Observers\BusinessObserver::class)->created($t->business->fresh());
        $this->assertSame(6, AnomalyRule::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_listado_de_reglas_aislado_por_negocio(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');

        $this->asUser($a->admin)->getJson('/api/v1/anomaly-rules')
            ->assertOk()->assertJsonCount(6, 'data');

        // Ninguna regla del negocio B aparece para A.
        $bRuleId = AnomalyRule::withoutGlobalScopes()->where('business_id', $b->business->id)->first()->id;
        $this->asUser($a->admin)->getJson('/api/v1/anomaly-rules')
            ->assertOk()
            ->assertJsonMissing(['id' => $bRuleId]);
    }

    public function test_actualizacion_de_regla_por_rol01(): void
    {
        $t = $this->seedTenant('a');
        $rule = AnomalyRule::withoutGlobalScopes()->where('business_id', $t->business->id)->where('code', 'descuadre_caja')->firstOrFail();

        $this->asUser($t->owner)->putJson("/api/v1/anomaly-rules/{$rule->id}", [
            'threshold_value'  => '50.00',
            'default_severity' => 'critica',
            'is_active'        => false,
        ])->assertOk()
            ->assertJsonPath('data.threshold_value', '50.00')
            ->assertJsonPath('data.default_severity', 'critica')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_actualizacion_de_regla_rechazada_para_rol02_y_rol03(): void
    {
        $t = $this->seedTenant('a');
        $rule = AnomalyRule::withoutGlobalScopes()->where('business_id', $t->business->id)->first();

        $this->asUser($t->admin)->putJson("/api/v1/anomaly-rules/{$rule->id}", ['is_active' => false])->assertStatus(403);
        $this->asUser($t->operator)->putJson("/api/v1/anomaly-rules/{$rule->id}", ['is_active' => false])->assertStatus(403);
    }

    public function test_codigo_de_regla_es_inmutable(): void
    {
        $t = $this->seedTenant('a');
        $rule = AnomalyRule::withoutGlobalScopes()->where('business_id', $t->business->id)->where('code', 'descuadre_caja')->firstOrFail();

        // Intento de cambiar code/name: se ignoran (fuera de $fillable); solo cambia el umbral.
        $this->asUser($t->owner)->putJson("/api/v1/anomaly-rules/{$rule->id}", [
            'code'            => 'venta_sin_sesion',
            'name'            => 'Hackeado',
            'threshold_value' => '10.00',
        ])->assertOk()->assertJsonPath('data.code', 'descuadre_caja')->assertJsonPath('data.threshold_value', '10.00');

        $this->assertSame('descuadre_caja', $rule->refresh()->code->value);
        $this->assertSame('Descuadre de caja', $rule->name);
    }

    // =======================================================================
    // Detección y umbral
    // =======================================================================

    public function test_deteccion_por_debajo_y_por_encima_del_umbral(): void
    {
        $t = $this->seedTenant('a');
        $rule = AnomalyRule::withoutGlobalScopes()->where('business_id', $t->business->id)->where('code', 'descuadre_caja')->firstOrFail();
        $this->asUser($t->owner)->putJson("/api/v1/anomaly-rules/{$rule->id}", ['threshold_value' => '50.00'])->assertOk();

        $this->closedCashSession($t, $t->operator, '100.00', '90.00');  // diferencia -10 (< 50): NO
        $above = $this->closedCashSession($t, $t->operator, '100.00', '0.00'); // diferencia -100 (>= 50): SÍ

        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 1);

        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        $this->assertSame($above->id, (int) $this->anomalyBySource($t, 'cash_sessions')->source_id);
    }

    // =======================================================================
    // Anomalías: listado, detalle, eventos, aislamiento
    // =======================================================================

    public function test_listado_de_anomalias_filtros_orden_y_paginacion(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '80.00');
        $this->closedCashSession($t, $t->operator, '200.00', '150.00');
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();

        $this->asUser($t->admin)->getJson('/api/v1/anomalies')
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.difference', fn ($v) => is_string($v));

        // Filtro por regla y por estado.
        $this->asUser($t->admin)->getJson('/api/v1/anomalies?rule_code=descuadre_caja&status=detectada')
            ->assertOk()->assertJsonPath('meta.total', 2);

        // Paginación.
        $this->asUser($t->admin)->getJson('/api/v1/anomalies?per_page=1')
            ->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonCount(1, 'data');

        // ROL-03 no lista (viewAny = ROL-02+).
        $this->asUser($t->operator)->getJson('/api/v1/anomalies')->assertStatus(403);
    }

    public function test_ordenamiento_no_permitido_devuelve_422(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->admin)->getJson('/api/v1/anomalies?sort=source_type')
            ->assertStatus(422)->assertJsonValidationErrors(['sort']);
    }

    public function test_detalle_y_eventos_sin_fuga_entre_tenants(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $this->closedCashSession($a, $a->operator, '100.00', '70.00');
        $this->runReconciliation($a, 'caja', $a->admin)->assertCreated();
        $anomaly = $this->anomalyBySource($a, 'cash_sessions');

        $this->asUser($a->admin)->getJson("/api/v1/anomalies/{$anomaly->id}")
            ->assertOk()->assertJsonPath('data.id', $anomaly->id)->assertJsonPath('data.rule.code', 'descuadre_caja');
        $this->asUser($a->admin)->getJson("/api/v1/anomalies/{$anomaly->id}/events")
            ->assertOk()->assertJsonPath('data.0.to_status', 'detectada');

        // El negocio B no ve la anomalía ni sus eventos (BusinessScope → 404).
        $this->asUser($b->admin)->getJson("/api/v1/anomalies/{$anomaly->id}")->assertNotFound();
        $this->asUser($b->admin)->getJson("/api/v1/anomalies/{$anomaly->id}/events")->assertNotFound();
    }

    // =======================================================================
    // Máquina de estados: justificación / resolución / BR-01 / eventos
    // =======================================================================

    public function test_justificacion_valida_crea_evento_y_coherencia(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '60.00'); // causante: operator
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();
        $anomaly = $this->anomalyBySource($t, 'cash_sessions');

        // admin (no causante) justifica con motivo.
        $this->asUser($t->admin)->postJson("/api/v1/anomalies/{$anomaly->id}/justify", ['reason' => 'Redondeo de vueltos'])
            ->assertOk()->assertJsonPath('data.status', 'justificada')->assertJsonPath('data.resolved_by', $t->admin->id);

        $anomaly->refresh();
        $this->assertNotNull($anomaly->resolved_at);
        // Evento automático detectada → justificada.
        $this->assertDatabaseHas('anomaly_events', [
            'anomaly_id' => $anomaly->id, 'from_status' => 'detectada', 'to_status' => 'justificada', 'comment' => 'Redondeo de vueltos',
        ]);
    }

    public function test_autojustificacion_rechazada_por_cada_origen(): void
    {
        $t = $this->seedTenant('a');
        // Fuentes CAUSADAS por admin (ROL-02, único que puede justificar).
        $this->closedCashSession($t, $t->admin, '100.00', '50.00');
        $this->physicalCount($t, $t->admin, '10.000', '7.000');
        $this->goodsReceipt($t, $t->admin, 'discrepancia');
        $this->runReconciliation($t, 'integral', $t->owner)->assertCreated();

        foreach (['cash_sessions', 'physical_counts', 'goods_receipts'] as $sourceType) {
            $anomaly = $this->anomalyBySource($t, $sourceType);
            // El causante (admin) NO puede justificar (BR-01, 403).
            $this->asUser($t->admin)->postJson("/api/v1/anomalies/{$anomaly->id}/justify", ['reason' => 'intento'])
                ->assertStatus(403)->assertJsonPath('code', 'SELF_RESOLUTION_NOT_ALLOWED');
            // Otro admin (no causante) sí puede.
            $this->asUser($t->admin2)->postJson("/api/v1/anomalies/{$anomaly->id}/justify", ['reason' => 'revisado'])
                ->assertOk()->assertJsonPath('data.status', 'justificada');
        }
    }

    public function test_transiciones_invalidas_controladas(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '60.00');
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();
        $anomaly = $this->anomalyBySource($t, 'cash_sessions');

        // Resolver (owner) → resuelta.
        $this->asUser($t->owner)->postJson("/api/v1/anomalies/{$anomaly->id}/resolve", ['comment' => 'ok'])->assertOk();

        // Re-justificar / re-resolver una anomalía ya resuelta → 422 controlado.
        $this->asUser($t->admin)->postJson("/api/v1/anomalies/{$anomaly->id}/justify", ['reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_ANOMALY_STATE');
        $this->asUser($t->owner)->postJson("/api/v1/anomalies/{$anomaly->id}/resolve", ['comment' => 'x'])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_ANOMALY_STATE');
    }

    public function test_resolucion_por_rol01_y_rechazo_a_rol02(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '60.00');
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();
        $anomaly = $this->anomalyBySource($t, 'cash_sessions');

        // ROL-02 no resuelve (resolve = ROL-01).
        $this->asUser($t->admin)->postJson("/api/v1/anomalies/{$anomaly->id}/resolve", ['comment' => 'x'])->assertStatus(403);
        // ROL-01 resuelve.
        $this->asUser($t->owner)->postJson("/api/v1/anomalies/{$anomaly->id}/resolve", ['comment' => 'Ajuste aplicado'])
            ->assertOk()->assertJsonPath('data.status', 'resuelta')->assertJsonPath('data.resolved_by', $t->owner->id);
    }

    public function test_inmutabilidad_de_anomaly_events(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '60.00');
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();
        $event = AnomalyEvent::withoutGlobalScopes()->where('business_id', $t->business->id)->firstOrFail();

        $this->expectException(\App\Exceptions\ImmutableRecordException::class);
        $event->update(['comment' => 'alterado']);
    }

    // =======================================================================
    // Deduplicación e idempotencia (candado activo)
    // =======================================================================

    public function test_deduplicacion_de_una_anomalia_activa(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '50.00');

        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 1);
        // Segunda corrida: la misma regla+origen ya tiene una activa ⇒ dedup silencioso, 0 nuevas.
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 0);

        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_colision_concurrente_respaldada_por_unique(): void
    {
        $t = $this->seedTenant('a');
        $session = $this->closedCashSession($t, $t->operator, '100.00', '50.00');
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();
        $anomaly = $this->anomalyBySource($t, 'cash_sessions');

        // Inserción directa de una SEGUNDA anomalía activa (misma regla+origen) ⇒ choque uniq_active_anomaly.
        try {
            DB::table('anomalies')->insert([
                'business_id'     => $anomaly->business_id,
                'anomaly_rule_id' => $anomaly->anomaly_rule_id,
                'severity'        => 'advertencia',
                'status'          => 'detectada',
                'source_type'     => 'cash_sessions',
                'source_id'       => $session->id,
                'detected_at'     => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $this->fail('El motor debía rechazar dos anomalías activas por regla+origen.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(1062, (int) ($e->errorInfo[1] ?? 0));
        }
    }

    public function test_liberacion_de_candado_permite_nueva_deteccion(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '50.00');
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();
        $anomaly = $this->anomalyBySource($t, 'cash_sessions');

        // Justificar libera el candado (active_dedupe_key → NULL).
        $this->asUser($t->admin)->postJson("/api/v1/anomalies/{$anomaly->id}/justify", ['reason' => 'ok'])->assertOk();

        // Nueva corrida sobre el mismo origen ⇒ una NUEVA anomalía activa.
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 1);
        $this->assertSame(2, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->where('source_type', 'cash_sessions')->count());
    }

    // =======================================================================
    // Corridas de conciliación
    // =======================================================================

    public function test_conciliacion_manual_por_cada_ambito(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '50.00');
        $this->physicalCount($t, $t->operator, '10.000', '6.000');
        $this->goodsReceipt($t, $t->operator, 'discrepancia');

        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated()
            ->assertJsonPath('data.scope', 'caja')->assertJsonPath('data.status', 'completada')->assertJsonPath('data.anomalies_found', 1);
        $this->runReconciliation($t, 'inventario_bodega', $t->admin)->assertCreated()
            ->assertJsonPath('data.anomalies_found', 1);
        $this->runReconciliation($t, 'compras_3way', $t->admin)->assertCreated()
            ->assertJsonPath('data.anomalies_found', 1);
        // Integral: los 3 ya tienen anomalía activa ⇒ dedup ⇒ 0 nuevas.
        $this->runReconciliation($t, 'integral', $t->admin)->assertCreated()
            ->assertJsonPath('data.scope', 'integral')->assertJsonPath('data.anomalies_found', 0);
    }

    public function test_registro_de_corrida_completo(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '50.00');

        $resp = $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();
        $resp->assertJsonPath('data.run_type', 'manual')
            ->assertJsonPath('data.triggered_by', $t->admin->id)
            ->assertJsonPath('data.status', 'completada')
            ->assertJsonPath('data.anomalies_found', 1);
        $this->assertNotNull($resp->json('data.started_at'));
        $this->assertNotNull($resp->json('data.finished_at'));

        // show incluye las anomalías de la corrida.
        $runId = $resp->json('data.id');
        $this->asUser($t->admin)->getJson("/api/v1/reconciliation-runs/{$runId}")
            ->assertOk()->assertJsonPath('data.id', $runId)->assertJsonCount(1, 'data.anomalies');
    }

    public function test_corrida_fallida_queda_marcada_como_fallida_sin_huerfanos(): void
    {
        $t = $this->seedTenant('a');
        $this->closedCashSession($t, $t->operator, '100.00', '50.00');

        // Colaborador que revienta durante la detección ⇒ la corrida debe caer a 'fallida'.
        $throwing = new class extends AnomalyService {
            public function registrarSilencioso(string $ruleCode, \Illuminate\Database\Eloquent\Model $source, array $values = []): ?\App\Models\Anomaly
            {
                throw new \RuntimeException('fallo simulado en detección');
            }
        };
        $service = new ReconciliationService($throwing);

        $run = $service->conciliar($t->business->id, ReconciliationScope::Caja, null, 'manual', $t->admin->id);

        $this->assertSame('fallida', $run->status->value);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(0, (int) $run->anomalies_found);
        // Sin anomalías ni eventos huérfanos.
        $this->assertSame(0, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        $this->assertSame(0, AnomalyEvent::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_conciliacion_no_modifica_registros_operativos(): void
    {
        $t = $this->seedTenant('a');
        $session = $this->closedCashSession($t, $t->operator, '100.00', '50.00');
        $before = $session->only(['status', 'expected_amount', 'counted_amount', 'closed_by']);

        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated();

        $after = $session->refresh()->only(['status', 'expected_amount', 'counted_amount', 'closed_by']);
        $this->assertSame($before, $after); // El motor solo LEE los registros operativos.
    }

    public function test_aislamiento_entre_negocios_en_corridas(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $this->closedCashSession($a, $a->operator, '100.00', '50.00');
        $runId = $this->runReconciliation($a, 'caja', $a->admin)->assertCreated()->json('data.id');

        // B no ve la corrida de A.
        $this->asUser($b->admin)->getJson("/api/v1/reconciliation-runs/{$runId}")->assertNotFound();
        // Una corrida de B no detecta nada del negocio A.
        $this->runReconciliation($b, 'caja', $b->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 0);
    }

    // =======================================================================
    // Hooks y comando programado
    // =======================================================================

    public function test_hook_cuenta_vencida_via_comando_mark_overdue(): void
    {
        $t = $this->seedTenant('a');
        $this->overdueReceivable($t);

        $this->artisan('receivables:mark-overdue')->assertExitCode(0);

        // Hook: la CxC vencida generó una anomalía 'cuenta_vencida' (source accounts_receivables).
        $anomaly = $this->anomalyBySource($t, 'accounts_receivables');
        $this->assertSame('detectada', $anomaly->status->value);
        $this->assertSame('cuenta_vencida', $anomaly->rule->code->value);
    }

    public function test_comando_reconciliation_recorre_multiples_tenants_sin_sesion(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $this->closedCashSession($a, $a->operator, '100.00', '40.00');
        $this->closedCashSession($b, $b->operator, '100.00', '30.00');

        // Sin usuario autenticado: el comando recorre cada negocio.
        $this->app['auth']->forgetGuards();
        $this->artisan('reconciliation:run --scope=caja')->assertExitCode(0);

        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $a->business->id)->count());
        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $b->business->id)->count());
        // Corridas 'programada' sin triggered_by en cada negocio.
        $this->assertSame(2, ReconciliationRun::withoutGlobalScopes()->whereIn('business_id', [$a->business->id, $b->business->id])->where('run_type', 'programada')->whereNull('triggered_by')->count());
    }

    // =======================================================================
    // Hooks INMEDIATOS (RF-11-03 · detección híbrida en línea + conciliación)
    // =======================================================================

    private function openCashSession(object $t, User $openedBy, string $opening = '100.00'): CashSession
    {
        $s = new CashSession();
        $s->forceFill([
            'business_id'      => $t->business->id,
            'cash_register_id' => $t->register->id,
            'opened_by'        => $openedBy->id,
            'status'           => 'abierta',
            'opening_amount'   => $opening,
            'opened_at'        => now(),
        ])->saveQuietly();

        return $s->refresh();
    }

    /** @return array{0: PurchaseOrder, 1: PurchaseOrderItem} */
    private function purchaseOrderWithItem(object $t, User $user): array
    {
        $supplier = new Supplier();
        $supplier->forceFill(['business_id' => $t->business->id, 'name' => 'Prov '.(++self::$seq), 'status' => 'aprobado', 'is_active' => true])->save();

        $po = new PurchaseOrder();
        $po->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $t->branch->id, 'supplier_id' => $supplier->id,
            'user_id' => $user->id, 'code' => 'OC-'.self::$seq, 'status' => 'emitida', 'expected_total' => '100.00',
            'ordered_at' => now()->toDateString(),
        ])->save();

        $poi = new PurchaseOrderItem();
        $poi->forceFill([
            'business_id' => $t->business->id, 'purchase_order_id' => $po->id, 'product_id' => $this->makeProduct($t)->id,
            'ordered_quantity' => '10.000', 'received_quantity' => '0.000', 'agreed_unit_cost' => '10.0000', 'line_total' => '100.00',
        ])->save();

        return [$po, $poi];
    }

    public function test_cierre_descuadrado_crea_anomalia_inmediata_enlazada_y_no_duplica(): void
    {
        $t = $this->seedTenant('a');
        $session = $this->openCashSession($t, $t->operator, '100.00');

        // Cierre con conteo 90 vs esperado 100 ⇒ descuadre. La sesión persiste y se emite 422 tras el commit.
        try {
            app(\App\Services\Cash\CashService::class)->cerrar($t->operator, $session, '90.00', [], null);
            $this->fail('Se esperaba UnreconciledCashClosingException.');
        } catch (\App\Exceptions\UnreconciledCashClosingException $e) {
            // esperado
        }

        // Anomalía INMEDIATA correctamente enlazada al origen.
        $anomaly = $this->anomalyBySource($t, 'cash_sessions');
        $this->assertSame('descuadre_caja', $anomaly->rule->code->value);
        $this->assertSame('detectada', $anomaly->status->value);
        $this->assertSame((int) $session->id, (int) $anomaly->source_id);
        $this->assertSame('-10.00', (string) $anomaly->difference);
        $this->assertNotNull($anomaly->resolveSource()); // enlazada, no huérfana

        // La sesión quedó descuadrada (evidencia confirmada).
        $this->assertSame('descuadrada', $session->refresh()->status->value);

        // Conciliación posterior NO duplica (dedup por uniq_active_anomaly).
        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 0);
        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_recepcion_discrepante_crea_anomalia_inmediata_enlazada_y_no_duplica(): void
    {
        $t = $this->seedTenant('a');
        [$po, $poi] = $this->purchaseOrderWithItem($t, $t->operator);
        $this->asUser($t->operator); // recepción bajo sesión autenticada (flujo real MOD-04).

        // Costo facturado (15) ≠ pactado (10) con tolerancia 0 ⇒ discrepancia 3-way.
        try {
            app(\App\Services\Purchasing\GoodsReceiptService::class)->recibir(
                $t->operator, $po->id, $t->warehouse->id,
                [['purchase_order_item_id' => $poi->id, 'received_quantity' => '10.000', 'invoiced_unit_cost' => '15.0000']],
                'INV-1', '150.00', '0.0000'
            );
            $this->fail('Se esperaba PurchaseMatchException.');
        } catch (\App\Exceptions\PurchaseMatchException $e) {
            // esperado
        }

        // La recepción y su evidencia persisten (match_status discrepancia).
        $receipt = GoodsReceipt::withoutGlobalScopes()->where('business_id', $t->business->id)->latest('id')->firstOrFail();
        $this->assertSame('discrepancia', $receipt->match_status->value);

        // Anomalía INMEDIATA enlazada al recibo.
        $anomaly = $this->anomalyBySource($t, 'goods_receipts');
        $this->assertSame('discrepancia_3way', $anomaly->rule->code->value);
        $this->assertSame((int) $receipt->id, (int) $anomaly->source_id);
        $this->assertNotNull($anomaly->resolveSource());

        // Conciliación 3-way posterior NO duplica.
        $this->runReconciliation($t, 'compras_3way', $t->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 0);
        $this->assertSame(1, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->where('source_type', 'goods_receipts')->count());
    }

    public function test_hook_deriva_tenant_del_origen_sin_depender_de_auth(): void
    {
        $t = $this->seedTenant('a');
        $session = $this->openCashSession($t, $t->operator, '100.00');

        // Sin sesión autenticada: el tenant de la anomalía se deriva del ORIGEN, no de Auth.
        $this->app['auth']->forgetGuards();

        try {
            app(\App\Services\Cash\CashService::class)->cerrar($t->operator, $session, '80.00', [], null);
        } catch (\App\Exceptions\UnreconciledCashClosingException $e) {
            // esperado
        }

        $anomaly = $this->anomalyBySource($t, 'cash_sessions');
        $this->assertSame((int) $t->business->id, (int) $anomaly->business_id);
        $this->assertSame('-20.00', (string) $anomaly->difference);
    }

    public function test_cierre_bajo_umbral_no_genera_anomalia(): void
    {
        $t = $this->seedTenant('a');
        $rule = AnomalyRule::withoutGlobalScopes()->where('business_id', $t->business->id)->where('code', 'descuadre_caja')->firstOrFail();
        $this->asUser($t->owner)->putJson("/api/v1/anomaly-rules/{$rule->id}", ['threshold_value' => '50.00'])->assertOk();

        $session = $this->openCashSession($t, $t->operator, '100.00');
        try {
            app(\App\Services\Cash\CashService::class)->cerrar($t->operator, $session, '90.00', [], null); // diff -10 (< 50)
        } catch (\App\Exceptions\UnreconciledCashClosingException $e) {
            // esperado: la sesión igual queda descuadrada
        }

        // Bajo umbral ⇒ ninguna anomalía (pero la evidencia del cierre persiste).
        $this->assertSame(0, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        $this->assertSame('descuadrada', $session->refresh()->status->value);
    }

    public function test_fallo_al_alertar_no_destruye_la_operacion_origen_ni_deja_huerfanos(): void
    {
        $t = $this->seedTenant('a');

        // Colaborador que revienta al alertar (peor caso): simula un fallo secundario en el hook.
        $this->app->instance(AnomalyService::class, new class extends AnomalyService {
            public function registrarSilencioso(string $ruleCode, \Illuminate\Database\Eloquent\Model $source, array $values = []): ?Anomaly
            {
                throw new \RuntimeException('fallo simulado al registrar la alerta');
            }
        });

        $session = $this->openCashSession($t, $t->operator, '100.00');
        try {
            app(\App\Services\Cash\CashService::class)->cerrar($t->operator, $session, '90.00', [], null);
            $this->fail('El doble debía propagar el fallo.');
        } catch (\Throwable $e) {
            // esperado
        }

        // La operación origen QUEDÓ confirmada (commit previo al hook) y NO hay anomalía huérfana.
        $this->assertSame('descuadrada', $session->refresh()->status->value);
        $this->assertSame('90.00', (string) $session->counted_amount);
        $this->assertSame(0, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        $this->assertSame(0, AnomalyEvent::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_venta_sin_sesion_detectada_por_conciliacion_caja(): void
    {
        $t = $this->seedTenant('a');

        // Cobro en efectivo con cash_session_id NULL (dato inconsistente): MOD-06/07 lo previenen en el
        // flujo real; este detector es una auditoría defensiva Fase 1.
        $customer = new Customer(['name' => 'Cli', 'document_type' => 'cedula', 'document_number' => 'DOC-'.(++self::$seq), 'credit_limit' => '0.00']);
        $customer->business_id = $t->business->id;
        $customer->save();

        $invoice = new Invoice();
        $invoice->forceFill([
            'business_id' => $t->business->id, 'branch_id' => $t->branch->id, 'customer_id' => $customer->id,
            'cash_session_id' => null, 'folio' => 'F-'.self::$seq, 'payment_type' => 'contado',
            'subtotal' => '50.00', 'tax_amount' => '0.00', 'discount_amount' => '0.00', 'total' => '50.00',
            'paid_amount' => '50.00', 'payment_status' => 'pagada', 'status' => 'emitida', 'issued_at' => now(), 'issued_by' => $t->operator->id,
        ])->saveQuietly();

        $ip = new InvoicePayment();
        $ip->forceFill([
            'business_id' => $t->business->id, 'invoice_id' => $invoice->id, 'cash_session_id' => null,
            'user_id' => $t->operator->id, 'payment_method' => 'efectivo', 'amount' => '50.00', 'paid_at' => now(),
        ])->save();

        $this->runReconciliation($t, 'caja', $t->admin)->assertCreated()->assertJsonPath('data.anomalies_found', 1);

        $anomaly = $this->anomalyBySource($t, 'invoice_payments');
        $this->assertSame('venta_sin_sesion', $anomaly->rule->code->value);
        $this->assertSame((int) $ip->id, (int) $anomaly->source_id);
    }

    // =======================================================================
    // Integridad del motor MySQL (information_schema)
    // =======================================================================

    public function test_integridad_de_esquema_mysql(): void
    {
        $db = DB::getDatabaseName();

        // active_dedupe_key: columna GENERADA + UNIQUE uniq_active_anomaly.
        $col = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'anomalies', 'active_dedupe_key'],
        );
        $this->assertNotNull($col);
        $this->assertStringContainsString('GENERATED', strtoupper((string) $col->EXTRA));

        $uniqActive = DB::selectOne(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$db, 'anomalies', 'uniq_active_anomaly'],
        );
        $this->assertNotNull($uniqActive);
        $this->assertSame(0, (int) $uniqActive->NON_UNIQUE);

        // uniq_anomaly_rule_code (una regla por código y negocio).
        $uniqRule = DB::selectOne(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'anomaly_rules', 'uniq_anomaly_rule_code', 'code'],
        );
        $this->assertNotNull($uniqRule);
        $this->assertSame(0, (int) $uniqRule->NON_UNIQUE);

        // CHECK de coherencia de resolución.
        $check = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ? AND CONSTRAINT_NAME = ?',
            [$db, 'anomalies', 'CHECK', 'chk_anomaly_resolution_coherence'],
        );
        $this->assertSame(1, (int) $check->c);

        // FKs: anomalies.anomaly_rule_id RESTRICT (no se borra una regla con anomalías);
        // anomaly_events.anomaly_id CASCADE (la bitácora vive y muere con su anomalía).
        $this->assertSame('RESTRICT', $this->deleteRule($db, 'anomalies', 'anomaly_rule_id'));
        $this->assertSame('CASCADE', $this->deleteRule($db, 'anomaly_events', 'anomaly_id'));

        // Índices de consulta.
        foreach (['idx_anomaly_status', 'idx_anomaly_severity', 'idx_anomaly_source'] as $index) {
            $exists = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$db, 'anomalies', $index],
            );
            $this->assertGreaterThan(0, (int) $exists->c, "Falta el índice {$index}.");
        }
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
