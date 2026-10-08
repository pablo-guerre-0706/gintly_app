<?php

declare(strict_types=1);

namespace Tests\Feature\ModSub;

use App\Contracts\SubscriptionGateway;
use App\Enums\RoleName;
use App\Models\BillingWebhookEvent;
use App\Models\Business;
use App\Models\CheckoutIntent;
use App\Models\PlanSubscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesBusinessSubscription;
use Tests\MysqlTestCase;
use Tests\Support\FakeSubscriptionGateway;

/**
 * MOD-SUB · Suscripción SaaS (Lemon Squeezy, modo prueba vía doble limpio). Verifica compuerta comercial,
 * checkout idempotente/coherencia de modo, ciclo de vida por webhook verificado (activación, duplicados,
 * cancelación, vencimiento, reembolso, firma/ modo/ correlación), límites de plan y una capacidad gateada.
 */
final class SubscriptionHttpTest extends MysqlTestCase
{
    use ActivatesBusinessSubscription;

    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(RolesAndPermissionsSeeder::class)->run();

        // Configuración COHERENTE (demo/test) con variantes/tienda/secreto y URLs controladas.
        config([
            'billing.deployment_purpose' => 'demo',
            'billing.provider_mode'      => 'test',
            'billing.store_id'           => 'store_1',
            'billing.webhook_secret'     => 'whsec_test_123',
            'billing.return_url'         => 'https://app.test/return',
            'billing.cancel_url'         => 'https://app.test/cancel',
            'billing.variants.test.basic.monthly'    => 'var_bm', 'billing.variants.test.basic.annual'    => 'var_ba',
            'billing.variants.test.comercio.monthly' => 'var_cm', 'billing.variants.test.comercio.annual' => 'var_ca',
            'billing.variants.test.cadena.monthly'   => 'var_dm', 'billing.variants.test.cadena.annual'   => 'var_da',
        ]);

        $this->app->instance(SubscriptionGateway::class, new FakeSubscriptionGateway());
    }

    // ---------------- Fixtures ----------------

    private function seedTenant(): object
    {
        $business = Business::query()->create([
            'name' => 'Neg '.(++self::$seq), 'slug' => 'neg-'.self::$seq,
            'plan' => 'basic', 'status' => 'trial', 'timezone' => 'America/Managua',
        ]);

        $owner = $this->makeUser($business, RoleName::Owner);
        $business->forceFill(['owner_user_id' => $owner->id])->save();
        $admin = $this->makeUser($business, RoleName::Admin);

        return (object) compact('business', 'owner', 'admin');
    }

    private function makeUser(Business $b, RoleName $role): User
    {
        $u = new User(['name' => $role->value.self::$seq, 'email' => 'u'.(++self::$seq).'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'branch_id' => null]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $u;
    }

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

    private function checkout(User $owner, string $plan, string $period, ?string $key = null)
    {
        return $this->asUser($owner)->postJson('/api/v1/billing/checkout', ['plan' => $plan, 'period' => $period], [
            'Idempotency-Key' => $key ?? (string) Str::uuid(),
        ]);
    }

    /** Envía un webhook firmado como Lemon Squeezy. */
    private function webhook(array $payload)
    {
        $raw = json_encode($payload);
        $sig = hash_hmac('sha256', (string) $raw, (string) config('billing.webhook_secret'));

        return $this->call('POST', '/api/v1/billing/webhook', [], [], [], [
            'CONTENT_TYPE'      => 'application/json',
            'HTTP_X_SIGNATURE'  => $sig,
        ], (string) $raw);
    }

    /** Payload estilo Lemon Squeezy. */
    private function lsEvent(string $event, string $type, string $id, array $attrs, array $custom): array
    {
        return [
            'meta' => ['event_name' => $event, 'custom_data' => $custom],
            'data' => [
                'type' => $type,
                'id'   => $id,
                'attributes' => array_merge([
                    'test_mode'  => true,
                    'store_id'   => (string) config('billing.store_id'),
                    'updated_at' => Carbon::now()->toIso8601String(),
                ], $attrs),
            ],
        ];
    }

    /** Lleva a un negocio hasta "activo" vía checkout + webhooks (created + payment_success). */
    private function activateViaWebhook(object $t, string $plan = 'cadena', string $period = 'annual'): array
    {
        $key = (string) Str::uuid();
        $this->checkout($t->owner, $plan, $period, $key)->assertCreated();
        $custom = ['business_id' => (string) $t->business->id, 'intent_key' => $key];

        $this->webhook($this->lsEvent('subscription_created', 'subscriptions', 'sub_'.$t->business->id, ['status' => 'active'], $custom))->assertOk();
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_'.$t->business->id.'_1', [
            'subscription_id' => 'sub_'.$t->business->id, 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial',
        ], $custom))->assertOk();

        return [$key];
    }

    private function fakeGateway(): FakeSubscriptionGateway
    {
        /** @var FakeSubscriptionGateway $g */
        $g = app(SubscriptionGateway::class);

        return $g;
    }

    private function branchPayload(object $t, string $name): array
    {
        return ['name' => $name, 'address' => 'Dir', 'manager_user_id' => $t->owner->id, 'opened_at' => Carbon::now()->toDateString()];
    }

    // =================================================================== Compuerta comercial

    public function test_negocio_sin_pago_no_opera_el_erp(): void
    {
        $t = $this->seedTenant();
        // ROL-01 sin suscripción: ruta operativa → 403 SUBSCRIPTION_REQUIRED.
        $this->asUser($t->owner)->getJson('/api/v1/stock')
            ->assertStatus(403)->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');
    }

    public function test_propietario_puede_identidad_situacion_y_contratacion_sin_pago(): void
    {
        $t = $this->seedTenant();
        $this->asUser($t->owner)->getJson('/api/v1/me')->assertOk();
        $this->asUser($t->owner)->getJson('/api/v1/billing/plans')->assertOk()->assertJsonCount(3, 'data');
        $this->asUser($t->owner)->getJson('/api/v1/billing/subscription')->assertOk()->assertJsonPath('data.grants_access', false);
        $co = $this->checkout($t->owner, 'cadena', 'annual')->assertCreated()->assertJsonPath('data.plan_key', 'cadena');
        $this->assertNotEmpty($co->json('data.checkout_url'));
    }

    public function test_pago_verificado_habilita_la_operacion(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');

        $this->asUser($t->owner)->getJson('/api/v1/billing/subscription')
            ->assertOk()->assertJsonPath('data.grants_access', true)->assertJsonPath('data.plan_key', 'cadena');
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk();
        $this->assertDatabaseHas('businesses', ['id' => $t->business->id, 'plan' => 'cadena']);
    }

    public function test_otros_usuarios_no_contratan(): void
    {
        $t = $this->seedTenant();
        // ROL-02 (no propietario) → 403 al contratar.
        $this->checkout($t->admin, 'cadena', 'annual')->assertStatus(403);
    }

    // =================================================================== Checkout

    public function test_checkout_idempotente_y_conflicto(): void
    {
        $t = $this->seedTenant();
        $key = (string) Str::uuid();

        $url1 = $this->checkout($t->owner, 'cadena', 'annual', $key)->assertCreated()->json('data.checkout_url');
        $url2 = $this->checkout($t->owner, 'cadena', 'annual', $key)->assertCreated()->json('data.checkout_url');
        $this->assertSame($url1, $url2);
        $this->assertSame(1, CheckoutIntent::query()->where('idempotency_key', $key)->count());

        // Misma clave, payload distinto → 409.
        $this->checkout($t->owner, 'comercio', 'monthly', $key)->assertStatus(409)->assertJsonPath('code', 'CHECKOUT_IDEMPOTENCY_CONFLICT');
    }

    public function test_cliente_no_puede_alterar_precio_tenant_ni_claves_extra(): void
    {
        $t = $this->seedTenant();
        $resp = $this->asUser($t->owner)->postJson('/api/v1/billing/checkout', [
            'plan' => 'cadena', 'period' => 'annual', 'business_id' => 999, 'amount' => 1, 'price' => 1, 'return_url' => 'https://evil.test',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $resp->assertStatus(422)->assertJsonValidationErrors(['business_id', 'amount', 'price', 'return_url']);
    }

    public function test_modo_incoherente_falla_en_cerrado(): void
    {
        $t = $this->seedTenant();
        config(['billing.provider_mode' => 'live']); // demo + live = incoherente
        $this->checkout($t->owner, 'cadena', 'annual')->assertStatus(503)->assertJsonPath('code', 'BILLING_UNAVAILABLE');
    }

    public function test_checkout_indisponible_si_falta_configuracion(): void
    {
        $t = $this->seedTenant();
        config(['billing.variants.test.cadena.annual' => null]); // variante ausente
        $this->checkout($t->owner, 'cadena', 'annual')->assertStatus(503)->assertJsonPath('code', 'BILLING_UNAVAILABLE');
    }

    // =================================================================== Webhook / ciclo de vida

    public function test_webhook_firma_invalida_400(): void
    {
        $t = $this->seedTenant();
        $raw = json_encode($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_x', [], []));
        $this->call('POST', '/api/v1/billing/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'mala'], (string) $raw)
            ->assertStatus(400);
        $this->assertSame(0, PlanSubscription::query()->where('business_id', $t->business->id)->count());
    }

    public function test_evento_duplicado_no_extiende_dos_veces(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t);
        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $paid = $sub->paid_until->toIso8601String();

        // Reenvío EXACTO del pago → idempotente, sin extender.
        $custom = ['business_id' => (string) $t->business->id, 'intent_key' => 'ignored'];
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_'.$t->business->id.'_1', [
            'subscription_id' => 'sub_'.$t->business->id, 'total' => 12000, 'currency' => 'USD',
        ], $custom))->assertOk();

        $this->assertSame($paid, $sub->refresh()->paid_until->toIso8601String());
        $this->assertSame(1, SubscriptionPayment::query()->where('business_id', $t->business->id)->count());
    }

    public function test_cancelacion_conserva_acceso_hasta_el_fin_pagado(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t);
        $this->webhook($this->lsEvent('subscription_cancelled', 'subscriptions', 'sub_'.$t->business->id, ['status' => 'cancelled'], []))->assertOk();

        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $this->assertSame('canceled', $sub->status->value);
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk(); // sigue con acceso (paid_until futuro)
    }

    public function test_vencimiento_bloquea_acceso(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t);

        // La vigencia pagada ya pasó; el vencedor la expira aunque el proveedor no notifique.
        PlanSubscription::query()->where('business_id', $t->business->id)->update(['paid_until' => Carbon::now()->subDay()]);
        app(SubscriptionService::class)->expireDue();

        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertStatus(403)->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');
    }

    public function test_reembolso_total_revoca_cobertura(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t);
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk();

        $this->webhook($this->lsEvent('subscription_payment_refunded', 'subscription-invoices', 'inv_'.$t->business->id.'_1', [
            'subscription_id' => 'sub_'.$t->business->id, 'refunded' => true,
        ], []))->assertOk();

        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertStatus(403);
    }

    public function test_evento_de_modo_distinto_no_activa(): void
    {
        $t = $this->seedTenant();
        $key = (string) Str::uuid();
        $this->checkout($t->owner, 'cadena', 'annual', $key)->assertCreated();
        $custom = ['business_id' => (string) $t->business->id, 'intent_key' => $key];

        // Recurso LIVE (test_mode=false) llegando a un backend en modo test → rechazado (sin activar).
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_live', [
            'subscription_id' => 'sub_x', 'test_mode' => false, 'total' => 12000, 'currency' => 'USD',
        ], $custom))->assertOk()->assertJsonPath('status', 'mode_mismatch');

        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertStatus(403);
    }

    public function test_evento_no_correlacionado_no_activa(): void
    {
        $t = $this->seedTenant();
        // business_id del payload sin intento propio que lo respalde.
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_z', [
            'subscription_id' => 'sub_z', 'total' => 12000, 'currency' => 'USD',
        ], ['business_id' => (string) $t->business->id, 'intent_key' => 'no-existe']))->assertOk()->assertJsonPath('status', 'uncorrelated');

        $this->assertSame(0, PlanSubscription::query()->where('business_id', $t->business->id)->count());
    }

    // =================================================================== Límites y capacidades

    public function test_limite_de_sucursales_del_plan(): void
    {
        $t = $this->seedTenant();
        $this->activateSubscription($t->business, 'basic'); // basic → 1 sucursal

        $payload = fn (string $n) => ['name' => $n, 'address' => 'Dir', 'manager_user_id' => $t->owner->id, 'opened_at' => Carbon::now()->toDateString()];
        $this->asUser($t->owner)->postJson('/api/v1/branches', $payload('Sucursal 1'))->assertCreated();
        $this->asUser($t->owner)->postJson('/api/v1/branches', $payload('Sucursal 2'))
            ->assertStatus(409)->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
    }

    public function test_capacidad_gateada_por_plan_supplier_map(): void
    {
        $t = $this->seedTenant();

        $this->activateSubscription($t->business, 'basic'); // basic NO incluye supplier_map
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertStatus(403)->assertJsonPath('code', 'PLAN_FEATURE_UNAVAILABLE');

        PlanSubscription::query()->where('business_id', $t->business->id)->update(['plan_key' => 'comercio']);
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk(); // comercio incluye supplier_map
    }

    public function test_suspension_administrativa_despues_de_iniciar_sesion_bloquea(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t); // negocio con pago vigente

        // Opera con normalidad (sesión iniciada, suscripción vigente, negocio no suspendido).
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk();

        // Suspensión administrativa decretada DESPUÉS del login (la cookie/sesión seguiría siendo válida).
        Business::query()->whereKey($t->business->id)->update(['status' => 'suspended']);

        // Las operaciones POSTERIORES quedan bloqueadas (403), aunque la suscripción siga pagada: control
        // INDEPENDIENTE; un pago no levanta la suspensión. Cubre API y, por el mismo EnsureOperableUser, web.
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertStatus(403);
        $this->asUser($t->owner)->getJson('/api/v1/billing/plans')->assertStatus(403);

        // La suscripción comercial sigue concediendo acceso por sí misma; la suspensión es el control separado.
        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $this->assertTrue($sub->grantsAccessNow());
        $this->assertFalse($t->business->fresh()->status->canOperate());
    }

    public function test_aislamiento_entre_negocios(): void
    {
        // Ambos negocios se siembran ANTES de actuar (evita el autollenado de business_id por la sesión).
        $a = $this->seedTenant();
        $b = $this->seedTenant();
        $this->activateViaWebhook($a, 'cadena', 'annual'); // SOLO A paga

        // A opera; el pago de A NO concede acceso a B.
        $this->asUser($a->owner)->getJson('/api/v1/stock')->assertOk();
        $this->asUser($b->owner)->getJson('/api/v1/stock')->assertStatus(403)->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');
        $this->asUser($b->owner)->getJson('/api/v1/billing/subscription')->assertOk()->assertJsonPath('data.grants_access', false);
    }

    // =================================================================== Webhook: recuperación / orden / prorrateo

    public function test_pago_fuera_de_orden_se_aparca_y_se_recupera(): void
    {
        $t = $this->seedTenant();
        $key = (string) Str::uuid();
        $this->checkout($t->owner, 'cadena', 'annual', $key)->assertCreated();
        $sid = 'sub_'.$t->business->id;

        // El pago llega ANTES de que la suscripción esté ligada localmente y SIN intent_key (fuera de orden):
        // evento PROPIO no correlacionable → se APARCA (recuperable), no se pierde ni activa.
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_ooo_1', [
            'subscription_id' => $sid, 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial',
        ], []))->assertOk()->assertJsonPath('status', 'parked');

        // Queda una fila APARCADA (parked_at no nulo, processed_at nulo): recuperable, no perdida.
        $this->assertSame(1, BillingWebhookEvent::query()
            ->where('event_name', 'subscription_payment_success')->where('payload', 'like', '%inv_ooo_1%')
            ->whereNotNull('parked_at')->whereNull('processed_at')->count());
        $this->assertSame(0, SubscriptionPayment::query()->where('business_id', $t->business->id)->count());
        $this->asUser($t->owner)->getJson('/api/v1/billing/subscription')->assertJsonPath('data.grants_access', false);

        // Llega subscription_created (liga la suscripción por intent_key). Aún sin pago aplicado.
        $this->webhook($this->lsEvent('subscription_created', 'subscriptions', $sid, ['status' => 'active'], ['business_id' => (string) $t->business->id, 'intent_key' => $key]))->assertOk();
        $this->asUser($t->owner)->getJson('/api/v1/billing/subscription')->assertJsonPath('data.grants_access', false);

        // La reconciliación reproduce el evento aparcado → ahora correlaciona y activa.
        $out = app(SubscriptionService::class)->reconcilePendingWebhooks();
        $this->assertSame(1, $out);
        $this->asUser($t->owner)->getJson('/api/v1/billing/subscription')->assertJsonPath('data.grants_access', true);
        $this->assertSame(1, SubscriptionPayment::query()->where('business_id', $t->business->id)->count());
    }

    public function test_evento_definitivamente_ajeno_no_se_aparca(): void
    {
        $t = $this->seedTenant();
        // intent_key que NO corresponde a ningún intento propio → ajeno: se marca procesado, NO se aparca.
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_foreign', [
            'subscription_id' => 'sub_foreign', 'total' => 12000, 'currency' => 'USD',
        ], ['business_id' => (string) $t->business->id, 'intent_key' => 'no-existe']))->assertOk()->assertJsonPath('status', 'uncorrelated');

        // La fila existe pero quedó PROCESADA (ajena) y SIN parked_at: no se reproduce en la reconciliación.
        $row = BillingWebhookEvent::query()->where('payload', 'like', '%inv_foreign%')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->parked_at);
        $this->assertNotNull($row->processed_at);
        $this->assertSame(0, app(SubscriptionService::class)->reconcilePendingWebhooks());
    }

    public function test_prorrateo_no_extiende_la_vigencia(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');
        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $paid = $sub->paid_until->toIso8601String();

        // Cobro de PRORRATEO (billing_reason=updated) con un id de factura NUEVO: se registra pero NO añade tiempo.
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_'.$t->business->id.'_proration', [
            'subscription_id' => 'sub_'.$t->business->id, 'total' => 3000, 'currency' => 'USD', 'billing_reason' => 'updated',
        ], []))->assertOk();

        $this->assertSame($paid, $sub->refresh()->paid_until->toIso8601String());
        $this->assertSame(2, SubscriptionPayment::query()->where('business_id', $t->business->id)->count());
    }

    public function test_doble_suscripcion_en_proveedor_no_extiende_dos_veces(): void
    {
        $t = $this->seedTenant();
        $keyA = (string) Str::uuid();
        $keyB = (string) Str::uuid();
        $this->checkout($t->owner, 'cadena', 'annual', $keyA)->assertCreated();

        // Segunda contratación en el PROVEEDOR para el mismo negocio (p. ej. una URL anterior pagada tras haberse
        // superado/expirado la vigente): se modela con un intento propio adicional, pues el endpoint reutiliza el
        // único checkout abierto. La defensa clave es que el webhook NO extienda dos veces aunque existan dos.
        CheckoutIntent::query()->create([
            'business_id' => $t->business->id, 'idempotency_key' => $keyB, 'plan_key' => 'cadena', 'period' => 'annual',
            'provider' => 'lemon_squeezy', 'provider_mode' => 'test', 'store_id' => 'store_1', 'provider_variant_id' => 'var_da',
            'status' => 'superseded', 'fingerprint' => hash('sha256', 'cadena|annual'),
        ]);

        // Se paga la suscripción A (sub_A) → activa.
        $customA = ['business_id' => (string) $t->business->id, 'intent_key' => $keyA];
        $this->webhook($this->lsEvent('subscription_created', 'subscriptions', 'sub_A', ['status' => 'active'], $customA))->assertOk();
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_A', [
            'subscription_id' => 'sub_A', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial',
        ], $customA))->assertOk();

        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $paid = $sub->paid_until->toIso8601String();

        // También se paga la suscripción B (sub_B, otra contratación en el proveedor para el MISMO negocio).
        $customB = ['business_id' => (string) $t->business->id, 'intent_key' => $keyB];
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_B', [
            'subscription_id' => 'sub_B', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial',
        ], $customB))->assertOk();

        // La vigencia NO se extiende por el doble cobro; queda constancia y se pide cancelar la duplicada.
        $this->assertSame($paid, $sub->refresh()->paid_until->toIso8601String());
        $this->assertSame(2, SubscriptionPayment::query()->where('business_id', $t->business->id)->count());
        $this->assertSame(1, SubscriptionPayment::query()->where('business_id', $t->business->id)->where('status', 'duplicate')->count());
        $this->assertContains('sub_B', array_column($this->fakeGateway()->cancelCalls, 'id'));
    }

    public function test_recuperacion_por_consulta_oficial_al_proveedor(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');
        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $paid = $sub->paid_until->copy();

        // El proveedor tiene una factura de RENOVACIÓN pagada cuyo webhook NUNCA llegó. La consulta oficial la recupera.
        $this->fakeGateway()->invoicesBySubscription['sub_'.$t->business->id] = [
            ['id' => 'inv_renew_lost', 'status' => 'paid', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'renewal', 'created_at' => null, 'refunded' => false],
            ['id' => 'inv_'.$t->business->id.'_1', 'status' => 'paid', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial', 'created_at' => null, 'refunded' => false], // ya contabilizada → dedup
        ];

        $recovered = app(SubscriptionService::class)->reconcileFromProvider();
        $this->assertSame(1, $recovered); // solo la perdida; la ya conocida se deduplica
        $this->assertTrue($sub->refresh()->paid_until->greaterThan($paid)); // renovación extendió la vigencia
    }

    public function test_factura_historica_recuperada_no_concede_tiempo_nuevo(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');
        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $paid = $sub->paid_until->copy();

        // El proveedor expone una factura HISTÓRICA (de hace 2 años) no registrada. Recuperarla deja constancia pero
        // su cobertura ya pasó: NO concede tiempo nuevo (se ancla en su fecha oficial).
        $this->fakeGateway()->invoicesBySubscription['sub_'.$t->business->id] = [
            ['id' => 'inv_historica', 'status' => 'paid', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'renewal', 'created_at' => Carbon::now()->subYears(2)->toIso8601String(), 'refunded' => false, 'variant_id' => 'var_da'],
        ];

        $this->assertSame(1, app(SubscriptionService::class)->reconcileFromProvider());
        $this->assertSame($paid->toIso8601String(), $sub->refresh()->paid_until->toIso8601String()); // sin tiempo nuevo
        $this->assertDatabaseHas('subscription_payments', ['provider_payment_id' => 'inv_historica']); // queda como evidencia
    }

    public function test_renovacion_perdida_se_recupera_para_suscripcion_vencida_localmente(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');
        // Vence LOCALMENTE (el webhook de renovación nunca llegó).
        PlanSubscription::query()->where('business_id', $t->business->id)->update(['status' => 'expired', 'paid_until' => Carbon::now()->subDay()]);
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertStatus(403);

        // El proveedor tiene una renovación PAGADA reciente (evidencia válida) + la inicial ya registrada (dedup).
        $this->fakeGateway()->invoicesBySubscription['sub_'.$t->business->id] = [
            ['id' => 'inv_renew_recovered', 'status' => 'paid', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'renewal', 'created_at' => Carbon::now()->subHours(2)->toIso8601String(), 'refunded' => false, 'variant_id' => 'var_da'],
            ['id' => 'inv_'.$t->business->id.'_1', 'status' => 'paid', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial', 'created_at' => Carbon::now()->subYear()->toIso8601String(), 'refunded' => false, 'variant_id' => 'var_da'],
        ];

        $this->assertSame(1, app(SubscriptionService::class)->reconcileFromProvider());
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk(); // reactivada SOLO con evidencia válida
    }

    public function test_reembolso_recibido_antes_del_pago_se_conserva_y_resuelve(): void
    {
        $t = $this->seedTenant();
        $key = (string) Str::uuid();
        $this->checkout($t->owner, 'cadena', 'annual', $key)->assertCreated();
        $sid = 'sub_'.$t->business->id;
        $custom = ['business_id' => (string) $t->business->id, 'intent_key' => $key];
        $this->webhook($this->lsEvent('subscription_created', 'subscriptions', $sid, ['status' => 'active'], $custom))->assertOk();

        // El REEMBOLSO llega ANTES que su pago (fuera de orden): se APARCA (recuperable), no se pierde ni revoca algo inexistente.
        $this->webhook($this->lsEvent('subscription_payment_refunded', 'subscription-invoices', 'inv_rf', ['subscription_id' => $sid, 'refunded' => true], $custom))
            ->assertOk()->assertJsonPath('status', 'parked');
        $this->assertSame(0, SubscriptionPayment::query()->where('business_id', $t->business->id)->count());

        // Llega el PAGO correspondiente → se registra y activa.
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_rf', ['subscription_id' => $sid, 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial'], $custom))->assertOk();
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk();

        // La reconciliación REPRODUCE el reembolso aparcado → ahora lo resuelve y revoca la cobertura.
        app(SubscriptionService::class)->reconcilePendingWebhooks();
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertStatus(403);
        $this->assertSame('refunded', (string) SubscriptionPayment::query()->where('provider_payment_id', 'inv_rf')->value('status'));
    }

    public function test_evento_expired_antiguo_no_retrocede_cobertura_renovada(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual'); // paid_until futuro (renovada/vigente)

        // subscription_expired OBSOLETO (fuera de orden) con la cobertura aún vigente: NO retrocede el estado confirmado.
        $this->webhook($this->lsEvent('subscription_expired', 'subscriptions', 'sub_'.$t->business->id, ['status' => 'expired'], []))->assertOk();

        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk(); // sigue con acceso
        $this->assertNotSame('expired', PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail()->status->value);
    }

    public function test_ascenso_confirmado_por_webhook_recibido_antes_de_responder_el_proveedor(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'basic', 'monthly'); // basic: sin supplier_map
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertStatus(403);
        $sid = 'sub_'.$t->business->id;

        // El pago del prorrateo llega MIENTRAS el PATCH al proveedor aún no responde. Como el cambio se persiste
        // ANTES de la red, el webhook ya encuentra el ascenso pendiente; se confirma porque el recurso OFICIAL del
        // proveedor (getSubscription) ya reporta la variante del cambio como la actual. La factura NO trae variante.
        $g = $this->fakeGateway();
        $g->onUpdateVariant = function () use ($g, $sid): void {
            $g->subscriptionVariant[$sid] = 'var_cm'; // el proveedor aplicó la variante al recibir el PATCH
            $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_upg_race', [
                'subscription_id' => $sid, 'total' => 3000, 'currency' => 'USD', 'billing_reason' => 'updated',
            ], []))->assertOk();
        };

        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'comercio', 'period' => 'monthly'])->assertOk();

        // El ascenso quedó confirmado por el pago que llegó antes de responder el proveedor.
        $this->assertDatabaseHas('businesses', ['id' => $t->business->id, 'plan' => 'comercio']);
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk();
    }

    public function test_recontratacion_tras_vencimiento_revincula_sin_marcar_duplicado(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual'); // ligada a sub_<biz>, activa
        // Vence localmente (sin vigencia).
        PlanSubscription::query()->where('business_id', $t->business->id)->update(['status' => 'expired', 'paid_until' => Carbon::now()->subDay()]);

        // RE-CONTRATACIÓN legítima: nueva contratación (otra clave) → el proveedor asigna OTRO provider_subscription_id.
        $key2 = (string) Str::uuid();
        CheckoutIntent::query()->create([
            'business_id' => $t->business->id, 'idempotency_key' => $key2, 'plan_key' => 'cadena', 'period' => 'annual',
            'provider' => 'lemon_squeezy', 'provider_mode' => 'test', 'store_id' => 'store_1', 'provider_variant_id' => 'var_da',
            'status' => 'created', 'fingerprint' => hash('sha256', 'cadena|annual'),
        ]);
        $custom2 = ['business_id' => (string) $t->business->id, 'intent_key' => $key2];
        $this->webhook($this->lsEvent('subscription_created', 'subscriptions', 'sub_NEW_'.$t->business->id, ['status' => 'active'], $custom2))->assertOk();
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_new_'.$t->business->id, [
            'subscription_id' => 'sub_NEW_'.$t->business->id, 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial',
        ], $custom2))->assertOk();

        // Se RE-VINCULA la MISMA fila (conserva business_id e historial) al NUEVO id; el pago NO es duplicado; activa.
        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $this->assertSame('sub_NEW_'.$t->business->id, (string) $sub->provider_subscription_id);
        $this->assertSame(1, PlanSubscription::query()->where('business_id', $t->business->id)->count());
        $this->assertSame(0, SubscriptionPayment::query()->where('business_id', $t->business->id)->where('status', 'duplicate')->count());
        $this->assertEmpty($this->fakeGateway()->cancelCalls); // no se canceló ninguna "paralela"
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk(); // reactivada legítimamente
    }

    public function test_reembolso_total_posterior_a_uno_parcial_revoca(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');
        $invId = 'inv_'.$t->business->id.'_1'; // el pago que sostiene la vigencia (creado por activateViaWebhook)
        $sid = 'sub_'.$t->business->id;

        // Reembolso PARCIAL del pago vigente: no revoca. (updated_at distinto ⇒ evento con identidad propia.)
        $this->webhook($this->lsEvent('subscription_payment_refunded', 'subscription-invoices', $invId, ['subscription_id' => $sid, 'refunded' => false, 'updated_at' => '2026-02-01T00:00:01Z'], []))->assertOk();
        $this->assertSame('partial_refund', (string) SubscriptionPayment::query()->where('provider_payment_id', $invId)->value('status'));
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk();

        // Reembolso TOTAL POSTERIOR del MISMO pago (otro evento): NO se ignora por el parcial previo → revoca.
        $this->webhook($this->lsEvent('subscription_payment_refunded', 'subscription-invoices', $invId, ['subscription_id' => $sid, 'refunded' => true, 'updated_at' => '2026-02-01T00:00:02Z'], []))->assertOk();
        $this->assertSame('refunded', (string) SubscriptionPayment::query()->where('provider_payment_id', $invId)->value('status'));
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertStatus(403);
    }

    // =================================================================== Gestión: cancelar / cambiar de plan

    public function test_cancelacion_por_endpoint_del_propietario(): void
    {
        // Ambos negocios se siembran ANTES de actuar como un usuario (como los demás fixtures): así el autollenado
        // de business_id por la sesión no contamina el aprovisionamiento del segundo negocio.
        $t = $this->seedTenant();
        $t2 = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');
        $this->activateViaWebhook($t2, 'cadena', 'annual');

        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/cancel')
            ->assertOk()->assertJsonPath('data.status', 'canceled');

        $this->assertContains('sub_'.$t->business->id, array_column($this->fakeGateway()->cancelCalls, 'id'));
        // Conserva acceso hasta el fin del período pagado.
        $this->asUser($t->owner)->getJson('/api/v1/stock')->assertOk();

        // Un no-propietario no cancela.
        $this->asUser($t2->admin)->postJson('/api/v1/billing/subscription/cancel')->assertStatus(403);
    }

    public function test_ascenso_eleva_capacidades_solo_tras_el_pago(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'basic', 'monthly'); // basic: sin supplier_map
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertStatus(403);

        // Solicita ASCENSO a comercio: cambia la variante YA en el proveedor (prorrateo inmediato) pero las
        // capacidades NO suben todavía.
        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'comercio', 'period' => 'monthly'])
            ->assertOk()->assertJsonPath('data.plan_key', 'basic')
            ->assertJsonPath('data.pending_change.plan_key', 'comercio');
        $this->assertTrue((bool) $this->fakeGateway()->updateCalls[0]['invoice_immediately']);
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertStatus(403); // aún no pagó el prorrateo

        // El proveedor ya aplicó la variante del ascenso (recurso oficial getSubscription). Llega el pago del
        // prorrateo (updated) → se confirma el ascenso (variante actual coincide con la del cambio): ahora hay supplier_map.
        $this->fakeGateway()->subscriptionVariant['sub_'.$t->business->id] = 'var_cm';
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_upg', [
            'subscription_id' => 'sub_'.$t->business->id, 'total' => 3000, 'currency' => 'USD', 'billing_reason' => 'updated',
        ], []))->assertOk();

        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk();
        $this->assertDatabaseHas('businesses', ['id' => $t->business->id, 'plan' => 'comercio']);
    }

    public function test_descenso_se_programa_al_fin_del_periodo_sin_borrar_recursos(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'comercio', 'monthly'); // comercio: 1 sucursal, 3 cajas
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'Suc 1'))->assertCreated();

        // Descenso a basic (1 sucursal): el uso (1 sucursal) cabe → se PROGRAMA al fin del período; el plan vigente
        // y la variante del proveedor con disable_prorations (no es programación), capacidades aún comercio.
        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'basic', 'period' => 'monthly'])
            ->assertOk()->assertJsonPath('data.plan_key', 'comercio')->assertJsonPath('data.pending_change.plan_key', 'basic');
        $this->assertTrue((bool) $this->fakeGateway()->updateCalls[0]['disable_prorations']);
        $this->assertFalse((bool) $this->fakeGateway()->updateCalls[0]['invoice_immediately']);

        // Al cerrar el período pagado, la reconciliación aplica el descenso (el proveedor ya tiene la variante basic;
        // sin borrar la sucursal existente).
        $this->fakeGateway()->subscriptionVariant['sub_'.$t->business->id] = 'var_bm';
        PlanSubscription::query()->where('business_id', $t->business->id)
            ->update(['pending_effective_at' => Carbon::now()->subMinute(), 'paid_until' => Carbon::now()->addDay()]);
        $applied = app(SubscriptionService::class)->applyDuePlanChanges();
        $this->assertSame(1, $applied);
        $this->assertDatabaseHas('businesses', ['id' => $t->business->id, 'plan' => 'basic']);
        $this->assertSame(1, \App\Models\Branch::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    public function test_descenso_rechazado_si_el_uso_no_cabe_sin_borrar_nada(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual'); // cadena: 5 sucursales
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'Suc 1'))->assertCreated();
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'Suc 2'))->assertCreated();

        // Descenso a basic (1 sucursal) con 2 sucursales activas → 409 PLAN_LIMIT_EXCEEDED; NO se borra nada.
        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'basic', 'period' => 'annual'])
            ->assertStatus(409)->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
        $this->assertSame(2, \App\Models\Branch::withoutGlobalScopes()->where('business_id', $t->business->id)->where('is_active', true)->count());
        $this->assertNull(PlanSubscription::query()->where('business_id', $t->business->id)->value('pending_plan_key'));
    }

    public function test_cambio_de_plan_requiere_suscripcion_y_rechaza_seleccion_identica(): void
    {
        $t = $this->seedTenant();
        // Sin suscripción vigente → NO_ACTIVE_SUBSCRIPTION.
        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'comercio', 'period' => 'monthly'])
            ->assertStatus(409)->assertJsonPath('code', 'NO_ACTIVE_SUBSCRIPTION');

        $this->activateViaWebhook($t, 'cadena', 'annual');
        // Selección idéntica a la vigente → 422 PLAN_CHANGE_INVALID.
        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'cadena', 'period' => 'annual'])
            ->assertStatus(422)->assertJsonPath('code', 'PLAN_CHANGE_INVALID');
    }

    // =================================================================== Checkout: ya activo / resultado incierto

    public function test_no_abre_segundo_checkout_si_ya_hay_suscripcion_vigente(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');

        $this->checkout($t->owner, 'cadena', 'annual')
            ->assertStatus(409)->assertJsonPath('code', 'SUBSCRIPTION_ALREADY_ACTIVE');
    }

    // =================================================================== Checkout: un solo abierto / expires_at

    public function test_segundo_checkout_clave_distinta_reutiliza_el_unico_abierto(): void
    {
        $t = $this->seedTenant();
        $r1 = $this->checkout($t->owner, 'cadena', 'annual')->assertCreated();
        $url1 = $r1->json('data.checkout_url');

        // expires_at FINITO presente y futuro.
        $this->assertNotNull($r1->json('data.expires_at'));
        $this->assertTrue(Carbon::parse($r1->json('data.expires_at'))->greaterThan(Carbon::now()));

        // Otra clave distinta, MISMA selección → reutiliza el ÚNICO checkout del proveedor (misma URL). Puede haber
        // una fila idempotente por clave, pero TODAS apuntan al mismo provider_checkout_id (una sola contratación
        // utilizable) y el proveedor se llamó una sola vez.
        $r2 = $this->checkout($t->owner, 'cadena', 'annual')->assertCreated();
        $this->assertSame($url1, $r2->json('data.checkout_url'));
        $ids = CheckoutIntent::query()->where('business_id', $t->business->id)->where('status', 'created')->pluck('provider_checkout_id')->filter()->unique();
        $this->assertCount(1, $ids);
        $this->assertCount(1, $this->fakeGateway()->checkoutCalls);
    }

    public function test_clave_que_reutiliza_checkout_abierto_conserva_su_idempotencia(): void
    {
        $t = $this->seedTenant();
        $keyA = (string) Str::uuid();
        $urlA = $this->checkout($t->owner, 'cadena', 'annual', $keyA)->assertCreated()->json('data.checkout_url');

        // Otra clave, MISMA selección → reutiliza el checkout abierto (misma URL) y PERSISTE su asociación idempotente.
        $keyB = (string) Str::uuid();
        $this->checkout($t->owner, 'cadena', 'annual', $keyB)->assertCreated()->assertJsonPath('data.checkout_url', $urlA);

        // keyB queda LIGADA: con el MISMO payload devuelve el mismo checkout…
        $this->checkout($t->owner, 'cadena', 'annual', $keyB)->assertCreated()->assertJsonPath('data.checkout_url', $urlA);
        // …y con OTRO payload NO se trata como si nunca hubiera respondido: 409 de conflicto de idempotencia.
        $this->asUser($t->owner)->postJson('/api/v1/billing/checkout', ['plan' => 'comercio', 'period' => 'monthly'], ['Idempotency-Key' => $keyB])
            ->assertStatus(409)->assertJsonPath('code', 'CHECKOUT_IDEMPOTENCY_CONFLICT');

        // Un SOLO checkout creado en el proveedor (la reutilización no vuelve a llamarlo).
        $this->assertCount(1, $this->fakeGateway()->checkoutCalls);
    }

    public function test_cambio_de_seleccion_con_checkout_vigente_se_bloquea(): void
    {
        $t = $this->seedTenant();
        $this->checkout($t->owner, 'cadena', 'annual')->assertCreated();

        // OTRA selección mientras el anterior sigue PAGABLE → 409 CHECKOUT_IN_PROGRESS: LS no permite invalidar un
        // checkout ya creado, así que NO se abre una segunda contratación (dos URLs pagables a la vez). Marcar
        // superseded localmente no lo haría impagable; por eso se bloquea.
        $this->checkout($t->owner, 'comercio', 'monthly')->assertStatus(409)->assertJsonPath('code', 'CHECKOUT_IN_PROGRESS');
        $this->assertSame(1, CheckoutIntent::query()->where('business_id', $t->business->id)->where('status', 'created')->count());

        // Cuando el anterior VENCE (expires_at finito, deja de ser pagable), sí se puede abrir otra selección.
        CheckoutIntent::query()->where('business_id', $t->business->id)->update(['expires_at' => Carbon::now()->subMinute()]);
        $this->checkout($t->owner, 'comercio', 'monthly')->assertCreated()->assertJsonPath('data.plan_key', 'comercio');
    }

    public function test_resultado_incierto_con_creacion_real_se_recupera_sin_duplicar(): void
    {
        $t = $this->seedTenant();
        $key1 = (string) Str::uuid();
        $g = $this->fakeGateway();
        // El proveedor SÍ creó el checkout (con el custom que fijamos), pero la respuesta se perdió.
        $g->checkoutsByIntentKey[$key1] = [
            'id' => 'co_real_1', 'url' => 'https://pay.test/co_real_1', 'expires_at' => Carbon::now()->addHour()->toIso8601String(),
            'store_id' => 'store_1', 'test_mode' => true, 'variant_id' => 'var_da',
            'business_id' => (string) $t->business->id, 'intent_key' => $key1,
        ];
        $g->failCheckoutWith = new \App\Exceptions\BillingUnavailableException();

        $this->checkout($t->owner, 'cadena', 'annual', $key1)->assertStatus(503);

        // Una clave NUEVA no recrea sin resolver: consulta al proveedor, VERIFICA (negocio/tienda/modo/variante),
        // RECUPERA el checkout real y lo reutiliza.
        $r = $this->checkout($t->owner, 'cadena', 'annual')->assertCreated();
        $this->assertSame('https://pay.test/co_real_1', $r->json('data.checkout_url'));
        // NO se duplicó el checkout del PROVEEDOR: todas las filas apuntan al mismo provider_checkout_id recuperado.
        $ids = CheckoutIntent::query()->where('business_id', $t->business->id)->pluck('provider_checkout_id')->filter()->unique()->values()->all();
        $this->assertSame(['co_real_1'], $ids);
    }

    public function test_resultado_incierto_sin_creacion_real_permite_recrear(): void
    {
        config(['billing.checkout_recovery_grace_seconds' => 0]); // ausencia inmediatamente concluyente para la prueba
        $t = $this->seedTenant();
        $key1 = (string) Str::uuid();
        $g = $this->fakeGateway();
        $g->failCheckoutWith = new \App\Exceptions\BillingUnavailableException(); // y NO consta en el proveedor
        $this->checkout($t->owner, 'cadena', 'annual', $key1)->assertStatus(503);

        // Pasada la gracia y con búsqueda CONCLUYENTE 'absent' → el intento se resuelve 'failed'; recién entonces se recrea.
        $g->failCheckoutWith = null;
        $this->checkout($t->owner, 'cadena', 'annual')->assertCreated();
        $this->assertSame('failed', (string) CheckoutIntent::query()->where('idempotency_key', $key1)->value('status'));
        $this->assertSame(1, CheckoutIntent::query()->where('business_id', $t->business->id)->where('status', 'created')->count());
    }

    public function test_resultado_incierto_dentro_de_gracia_o_busqueda_incompleta_bloquea(): void
    {
        $t = $this->seedTenant();
        $key1 = (string) Str::uuid();
        $g = $this->fakeGateway();
        $g->failCheckoutWith = new \App\Exceptions\BillingUnavailableException();
        $this->checkout($t->owner, 'cadena', 'annual', $key1)->assertStatus(503);

        // Ausente DENTRO de la ventana de gracia (la creación podría seguir procesándose): se conserva incierto y se
        // bloquea otra creación (no se recrea a ciegas).
        $g->failCheckoutWith = null; // la creación ya no fallaría, pero la resolución del incierto debe bloquear
        $this->checkout($t->owner, 'cadena', 'annual')->assertStatus(409)->assertJsonPath('code', 'CHECKOUT_RESULT_UNKNOWN');

        // Búsqueda INCOMPLETA (no concluyente) también bloquea, aun pasada la gracia.
        config(['billing.checkout_recovery_grace_seconds' => 0]);
        $g->findOutcomeDefault = 'incomplete';
        $this->checkout($t->owner, 'cadena', 'annual')->assertStatus(409)->assertJsonPath('code', 'CHECKOUT_RESULT_UNKNOWN');
    }

    public function test_resultado_incierto_sin_poder_consultar_no_recrea(): void
    {
        $t = $this->seedTenant();
        $key1 = (string) Str::uuid();
        $g = $this->fakeGateway();
        $g->failCheckoutWith = new \App\Exceptions\BillingUnavailableException();
        $this->checkout($t->owner, 'cadena', 'annual', $key1)->assertStatus(503);

        // Si el proveedor NO se puede consultar para resolver el incierto → 409 CHECKOUT_RESULT_UNKNOWN: jamás se
        // recrea a ciegas (podría dejar dos checkouts pagables). El cliente reintenta cuando el proveedor responda.
        $g->failFindWith = new \RuntimeException('provider down');
        $this->checkout($t->owner, 'cadena', 'annual')->assertStatus(409)->assertJsonPath('code', 'CHECKOUT_RESULT_UNKNOWN');
    }

    public function test_checkout_vencido_no_bloquea_uno_nuevo(): void
    {
        $t = $this->seedTenant();
        $r1 = $this->checkout($t->owner, 'cadena', 'annual')->assertCreated();

        // Vence la URL actual (expires_at finito en el pasado): deja de ser utilizable.
        CheckoutIntent::query()->where('business_id', $t->business->id)->update(['expires_at' => Carbon::now()->subMinute()]);

        // Una clave nueva NO reutiliza el vencido; crea uno fresco (recuperación).
        $r2 = $this->checkout($t->owner, 'cadena', 'annual')->assertCreated();
        $this->assertNotSame($r1->json('data.checkout_url'), $r2->json('data.checkout_url'));
        $this->assertSame(2, CheckoutIntent::query()->where('business_id', $t->business->id)->count());
    }

    // =================================================================== Reactivación de sucursales (límite)

    public function test_reactivacion_de_sucursal_respeta_el_limite_del_plan(): void
    {
        $t = $this->seedTenant();
        $this->activateSubscription($t->business, 'basic'); // basic: 1 sucursal activa

        $s1 = $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'S1'))->assertCreated()->json('data.id');
        // Desactivar S1 (no toca el cupo) y crear S2 (0 activas → cabe).
        $this->asUser($t->owner)->putJson("/api/v1/branches/{$s1}", ['is_active' => false])->assertOk();
        $s2 = $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'S2'))->assertCreated()->json('data.id');

        // Reactivar S1 con S2 activa → excede basic(1): 409 PLAN_LIMIT_EXCEEDED (la reactivación pasa por la guarda).
        $this->asUser($t->owner)->putJson("/api/v1/branches/{$s1}", ['is_active' => true])
            ->assertStatus(409)->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');

        // Si se libera cupo (desactivar S2), la reactivación procede (sin borrar nada).
        $this->asUser($t->owner)->putJson("/api/v1/branches/{$s2}", ['is_active' => false])->assertOk();
        $this->asUser($t->owner)->putJson("/api/v1/branches/{$s1}", ['is_active' => true])->assertOk();
        $this->assertSame(2, \App\Models\Branch::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
    }

    // =================================================================== Descenso: coordinación con el scheduler

    public function test_renovacion_aplica_el_descenso_coherente_con_lo_cobrado(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual'); // cadena (0 sucursales aún)

        // Programa descenso a basic (0 sucursales cabe). Variante cambiada en el proveedor SIN cobro inmediato.
        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'basic', 'period' => 'annual'])
            ->assertOk()->assertJsonPath('data.pending_change.plan_key', 'basic');
        $this->assertFalse((bool) $this->fakeGateway()->updateCalls[0]['invoice_immediately']); // sin cobro incorrecto

        // Durante el descenso pendiente las ALTAS se acotan al DESTINO (basic=1): se MANTIENE que el uso quepa hasta
        // la renovación (grandfathering RETIRADO). S1 cabe; S2 se bloquea. El plan vigente sigue siendo cadena.
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'S1'))->assertCreated();
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'S2'))->assertStatus(409)->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
        $this->assertSame('cadena', (string) PlanSubscription::query()->where('business_id', $t->business->id)->value('plan_key'));

        // Cierra el período; el scheduler NO corre. El proveedor YA aplicó la variante del descenso (recurso oficial
        // getSubscription) y la RENOVACIÓN la cobra: el webhook deja el estado coherente con lo cobrado,
        // independiente del scheduler. La subscription-invoice NO trae variante (campo oficial inexistente).
        $this->fakeGateway()->subscriptionVariant['sub_'.$t->business->id] = 'var_ba';
        PlanSubscription::query()->where('business_id', $t->business->id)
            ->update(['pending_effective_at' => Carbon::now()->subMinute(), 'paid_until' => Carbon::now()->subMinute()]);
        $this->webhook($this->lsEvent('subscription_payment_success', 'subscription-invoices', 'inv_renew_'.$t->business->id, [
            'subscription_id' => 'sub_'.$t->business->id, 'total' => 70000, 'currency' => 'USD', 'billing_reason' => 'renewal',
        ], []))->assertOk();

        // Coherencia plan/periodo/variante/capacidades/límites con lo cobrado; recursos existentes conservados (sin borrar).
        $this->assertDatabaseHas('businesses', ['id' => $t->business->id, 'plan' => 'basic']);
        $sub = PlanSubscription::query()->where('business_id', $t->business->id)->firstOrFail();
        $this->assertSame('basic', (string) $sub->plan_key);
        $this->assertSame('var_ba', (string) $sub->provider_variant_id); // variante coherente con la cobrada
        $this->assertTrue($sub->paid_until->greaterThan(Carbon::now()));
        $this->assertSame(1, \App\Models\Branch::withoutGlobalScopes()->where('business_id', $t->business->id)->where('is_active', true)->count());
        // El límite basic(1) ya rige; la capacidad de plan superior ya no está.
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'S3'))->assertStatus(409)->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertStatus(403)->assertJsonPath('code', 'PLAN_FEATURE_UNAVAILABLE');
        // Evidencia: importe cobrado y catálogo del plan destino (basic anual = 1 392 000 centavos NIO).
        $this->assertDatabaseHas('subscription_payments', [
            'provider_payment_id' => 'inv_renew_'.$t->business->id, 'amount_minor' => 70000, 'catalog_amount_minor' => 1392000,
        ]);
    }

    public function test_descenso_lo_aplica_el_scheduler_como_respaldo_sin_renovacion(): void
    {
        $t = $this->seedTenant();
        $this->activateViaWebhook($t, 'cadena', 'annual');
        $this->asUser($t->owner)->postJson('/api/v1/billing/subscription/change', ['plan' => 'basic', 'period' => 'annual'])->assertOk();
        // Durante el pendiente, las altas se acotan a basic(1): solo 1 cabe (así el uso siempre encaja al aplicar).
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'S1'))->assertCreated();
        $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'S2'))->assertStatus(409);

        // Cierra el período; NO llega la renovación. El proveedor ya tiene la variante del descenso (getSubscription).
        // El scheduler la verifica y aplica el descenso igualmente (el uso cabe), sin borrar.
        $this->fakeGateway()->subscriptionVariant['sub_'.$t->business->id] = 'var_ba';
        PlanSubscription::query()->where('business_id', $t->business->id)
            ->update(['pending_effective_at' => Carbon::now()->subDay(), 'paid_until' => Carbon::now()->addDay()]);
        $this->assertSame(1, app(SubscriptionService::class)->applyDuePlanChanges());
        $this->assertSame('basic', (string) PlanSubscription::query()->where('business_id', $t->business->id)->value('plan_key'));
        $this->assertNull(PlanSubscription::query()->where('business_id', $t->business->id)->value('pending_plan_key'));
        $this->assertSame(1, \App\Models\Branch::withoutGlobalScopes()->where('business_id', $t->business->id)->where('is_active', true)->count());
        // Idempotente: una segunda corrida no vuelve a aplicar.
        $this->assertSame(0, app(SubscriptionService::class)->applyDuePlanChanges());
    }

    // =================================================================== Límite: infraestructura ≠ cupo superado

    public function test_timeout_de_lock_no_se_reporta_como_cupo_superado(): void
    {
        $t = $this->seedTenant();
        $this->activateSubscription($t->business, 'basic'); // basic: 1 sucursal
        config(['billing.lock_timeout_seconds' => 1]);      // espera finita y corta

        // Una conexión SEPARADA retiene el lock de la comprobación de sucursales de este negocio.
        config(['database.connections.lockholder' => config('database.connections.mysql')]);
        $holder = DB::connection('lockholder');
        $lockName = 'gintly_plan_branches_'.$t->business->id;
        $holder->select('SELECT GET_LOCK(?, 5) AS l', [$lockName]);

        try {
            // La creación no puede SERIALIZAR su comprobación → 503 LIMIT_CHECK_UNAVAILABLE (NO 409 de cupo),
            // y no se crea ninguna sucursal (fail-closed sin afirmar un exceso).
            $this->asUser($t->owner)->postJson('/api/v1/branches', $this->branchPayload($t, 'Suc X'))
                ->assertStatus(503)->assertJsonPath('code', 'LIMIT_CHECK_UNAVAILABLE');
            $this->assertSame(0, \App\Models\Branch::withoutGlobalScopes()->where('business_id', $t->business->id)->count());
        } finally {
            $holder->select('SELECT RELEASE_LOCK(?)', [$lockName]);
            $holder->disconnect();
            DB::purge('lockholder');
        }
    }
}
