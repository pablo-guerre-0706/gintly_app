<?php

declare(strict_types=1);

namespace Tests\Feature\ModSub;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aceptación de CONCURRENCIA REAL de MOD-SUB. Como RegisterConcurrencyTest: NO envuelve el test en transacción;
 * lanza DOS procesos PHP (proc_open) con conexiones MySQL independientes que CONFIRMAN de verdad, sincronizados
 * por una barrera de archivos con timeout finito. Ejecuta los servicios REALES (SubscriptionService/PlanLimits)
 * con GET_LOCK y escrituras reales; el proveedor se sustituye por un doble limpio. Fixtures mínimos insertados
 * SIN el observer (DB::table) y limpieza acotada por token.
 *
 * Escenarios: checkout (misma clave), webhook de pago duplicado, última sucursal permitida, última caja
 * simultánea permitida.
 *
 * Guardas: APP_ENV=testing, base en la allowlist, opt-in GINTLY_MYSQL_TESTS y proc_open disponible.
 */
final class SubscriptionConcurrencyTest extends TestCase
{
    private const DB_NAME = 'gintly_backend_claude';

    private string $token = '';

    private string $barrierDir = '';

    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', self::DB_NAME);
        $app['config']->set('database.connections.mysql.url', null);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->environment('production')) {
            $this->fail('PROHIBIDO ejecutar pruebas MySQL con APP_ENV=production.');
        }
        if ((string) $this->app->environment() !== 'testing') {
            $this->markTestSkipped('Requiere APP_ENV=testing.');
        }
        if (! filter_var(env('GINTLY_MYSQL_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Pruebas MySQL desactivadas. Active GINTLY_MYSQL_TESTS=1.');
        }
        if ((string) config('database.connections.mysql.database') !== self::DB_NAME) {
            $this->fail('Base MySQL no autorizada para pruebas.');
        }
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open no disponible: no se puede probar concurrencia multiproceso.');
        }
        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Conexión MySQL no disponible: '.$e->getMessage());
        }

        $this->token = 'QA-SUBCONC-'.Str::random(10);
        $this->barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gintly_subconc_'.Str::random(10);
        @mkdir($this->barrierDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupToken();
        $this->rrmdir($this->barrierDir);

        parent::tearDown();
    }

    // ============================= Escenarios =============================

    public function test_checkout_misma_clave_concurrente_un_solo_intento(): void
    {
        $bizId = $this->seedBusiness(); // sin suscripción: checkout permitido
        $key = (string) Str::uuid();

        [$a, $b] = $this->runPair([
            ['scenario' => 'checkout', 'business_id' => $bizId, 'key' => $key],
            ['scenario' => 'checkout', 'business_id' => $bizId, 'key' => $key],
        ]);

        $this->assertTrue($a['ok'] ?? false, 'A: '.json_encode($a));
        $this->assertTrue($b['ok'] ?? false, 'B: '.json_encode($b));

        // Idempotencia bajo concurrencia real: UN solo intento de checkout y una URL coherente para ambos.
        $this->assertSame(1, DB::table('checkout_intents')->where('idempotency_key', $key)->count());
        $this->assertSame($a['url'], $b['url']);
        $this->assertSame((int) $a['intent_id'], (int) $b['intent_id']);
    }

    public function test_checkout_claves_distintas_concurrentes_un_solo_utilizable(): void
    {
        $bizId = $this->seedBusiness(); // sin suscripción: checkout permitido
        $keyA = (string) Str::uuid();
        $keyB = (string) Str::uuid();

        [$a, $b] = $this->runPair([
            ['scenario' => 'checkout', 'business_id' => $bizId, 'key' => $keyA],
            ['scenario' => 'checkout', 'business_id' => $bizId, 'key' => $keyB],
        ]);

        $this->assertTrue($a['ok'] ?? false, 'A: '.json_encode($a));
        $this->assertTrue($b['ok'] ?? false, 'B: '.json_encode($b));

        // Dos claves DISTINTAS simultáneas, misma selección → un ÚNICO checkout UTILIZABLE (el segundo reutiliza
        // el abierto). Cada clave conserva su propia asociación idempotente ('created'), pero ambas apuntan al
        // MISMO checkout del proveedor: no quedan dos contrataciones utilizables en paralelo ni dos checkouts reales.
        $this->assertSame(
            1,
            DB::table('checkout_intents')->where('business_id', $bizId)->where('status', 'created')
                ->distinct()->count('provider_checkout_id'),
            'Debe existir un ÚNICO checkout del proveedor utilizable: '
                .json_encode(DB::table('checkout_intents')->where('business_id', $bizId)->get(['idempotency_key', 'status', 'provider_checkout_id'])->all())
        );
        $this->assertSame($a['url'], $b['url']);
    }

    public function test_reactivacion_y_alta_concurrentes_respetan_el_limite(): void
    {
        $bizId = $this->seedBusiness();
        $this->seedSubscription($bizId, plan: 'basic', status: 'active', providerSubId: 'sub_r', paidUntil: now()->addYear());
        $inactive = $this->seedBranch($bizId, active: false); // 1 sucursal INACTIVA, 0 activas

        [$a, $b] = $this->runPair([
            ['scenario' => 'branch_reactivate', 'business_id' => $bizId, 'branch_id' => $inactive],
            ['scenario' => 'branch', 'business_id' => $bizId],
        ]);

        // basic permite 1 activa: reactivación y alta compiten por el único cupo bajo el mismo lock.
        $this->assertExactlyOneWinnerAndLimit($a, $b);
        $this->assertSame(1, DB::table('branches')->where('business_id', $bizId)->whereNull('deleted_at')->where('is_active', true)->count());
    }

    public function test_webhook_pago_duplicado_concurrente_no_extiende_dos_veces(): void
    {
        $bizId = $this->seedBusiness();
        // Suscripción ligada al proveedor, aún sin vigencia: el pago la activará (una sola vez).
        $this->seedSubscription($bizId, plan: 'cadena', status: 'incomplete', providerSubId: 'sub_conc', paidUntil: null);

        $payload = $this->paymentPayload('sub_conc', 'inv_conc');
        [$a, $b] = $this->runPair([
            ['scenario' => 'webhook', 'business_id' => $bizId, 'payload' => $payload],
            ['scenario' => 'webhook', 'business_id' => $bizId, 'payload' => $payload],
        ]);

        $this->assertTrue($a['ok'] ?? false, 'A: '.json_encode($a));
        $this->assertTrue($b['ok'] ?? false, 'B: '.json_encode($b));
        $this->assertContains($a['result'], ['processed', 'duplicate']);
        $this->assertContains($b['result'], ['processed', 'duplicate']);

        // UN solo pago contabilizado y UNA sola extensión de vigencia (sin doble conteo).
        $this->assertSame(1, DB::table('subscription_payments')->where('business_id', $bizId)->count());
        $sub = DB::table('plan_subscriptions')->where('business_id', $bizId)->first();
        $this->assertSame('active', $sub->status);
        $this->assertNotNull($sub->paid_until);
    }

    public function test_dos_pagos_distintos_concurrentes_conservan_sus_periodos(): void
    {
        $bizId = $this->seedBusiness();
        $this->seedSubscription($bizId, plan: 'cadena', status: 'active', providerSubId: 'sub_two', paidUntil: now());

        // Dos facturas DISTINTAS (renovaciones con FECHAS oficiales distintas) para la MISMA suscripción, a la vez.
        // La relectura CON BLOQUEO serializa (sin lost-update) y cada factura cubre SU período (anclado a su fecha):
        // la vigencia es el MÁXIMO período_end, NO la suma. Ambos pagos quedan registrados con su período.
        $early = now()->copy();
        $late  = now()->copy()->addMonth();
        [$a, $b] = $this->runPair([
            ['scenario' => 'webhook', 'business_id' => $bizId, 'payload' => $this->renewalPayload('sub_two', 'inv_two_A', $early->toIso8601String())],
            ['scenario' => 'webhook', 'business_id' => $bizId, 'payload' => $this->renewalPayload('sub_two', 'inv_two_B', $late->toIso8601String())],
        ]);

        $this->assertTrue($a['ok'] ?? false, 'A: '.json_encode($a));
        $this->assertTrue($b['ok'] ?? false, 'B: '.json_encode($b));

        // Ambos pagos distintos contabilizados; la vigencia = período MÁS LEJANO (≈ fecha tardía + 1 año), SIN sumar
        // (sumar daría ≈ 2 años). Ventana (12,18) meses distingue máximo (~13) de suma (~24).
        $this->assertSame(2, DB::table('subscription_payments')->where('business_id', $bizId)->count());
        $paidUntil = \Illuminate\Support\Carbon::parse(DB::table('plan_subscriptions')->where('business_id', $bizId)->value('paid_until'));
        $this->assertTrue($paidUntil->greaterThan(now()->addMonths(12)), 'aplicó al menos la renovación tardía');
        $this->assertTrue($paidUntil->lessThan(now()->addMonths(18)), 'NO sumó cobertura (sería ~24 meses)');
    }

    public function test_ultima_sucursal_permitida_bajo_concurrencia(): void
    {
        $bizId = $this->seedBusiness();
        $this->seedSubscription($bizId, plan: 'basic', status: 'active', providerSubId: 'sub_b', paidUntil: now()->addYear());

        [$a, $b] = $this->runPair([
            ['scenario' => 'branch', 'business_id' => $bizId],
            ['scenario' => 'branch', 'business_id' => $bizId],
        ]);

        // Exactamente UNA creación válida; la otra, cupo REALMENTE superado (409), nunca un falso infra-error.
        $this->assertExactlyOneWinnerAndLimit($a, $b);
        $this->assertSame(1, DB::table('branches')->where('business_id', $bizId)->whereNull('deleted_at')->count());
    }

    public function test_ultima_caja_simultanea_permitida_bajo_concurrencia(): void
    {
        $bizId = $this->seedBusiness();
        $this->seedSubscription($bizId, plan: 'basic', status: 'active', providerSubId: 'sub_c', paidUntil: now()->addYear());
        $userId = $this->seedUser($bizId);
        $branchId = $this->seedBranch($bizId);
        $reg1 = $this->seedRegister($bizId, $branchId, 'Caja 1');
        $reg2 = $this->seedRegister($bizId, $branchId, 'Caja 2');

        [$a, $b] = $this->runPair([
            ['scenario' => 'cash', 'business_id' => $bizId, 'register_id' => $reg1, 'user_id' => $userId],
            ['scenario' => 'cash', 'business_id' => $bizId, 'register_id' => $reg2, 'user_id' => $userId],
        ]);

        $this->assertExactlyOneWinnerAndLimit($a, $b);
        $this->assertSame(1, DB::table('cash_sessions')->where('business_id', $bizId)->whereIn('status', ['abierta', 'descuadrada'])->count());
    }

    public function test_descenso_y_alta_de_sucursal_concurrentes_se_coordinan(): void
    {
        $bizId = $this->seedBusiness();
        // Cadena (5 sucursales) con 1 activa. Descenso a basic (1 sucursal): el uso ACTUAL (1) cabe justo en el destino.
        $this->seedSubscription($bizId, plan: 'cadena', status: 'active', providerSubId: 'sub_dg_b', paidUntil: now()->addYear());
        $this->seedBranch($bizId); // 1 sucursal activa (== límite de basic)

        // A: DESCENSO cadena→basic (programa el pendiente bajo el lock de sucursales). B: ALTA de sucursal.
        // Compiten por el MISMO lock: o se programa el descenso y el alta se rechaza (cupo del destino), o el alta
        // entra (2ª) y el descenso se rechaza porque el uso ya no cabría en basic. Nunca ambos.
        [$a, $b] = $this->runPair([
            ['scenario' => 'downgrade', 'business_id' => $bizId, 'target_plan' => 'basic', 'target_period' => 'annual'],
            ['scenario' => 'branch', 'business_id' => $bizId],
        ]);

        $this->assertExactlyOneWinnerAndLimit($a, $b);

        $pending  = DB::table('plan_subscriptions')->where('business_id', $bizId)->value('pending_plan_key');
        $branches = DB::table('branches')->where('business_id', $bizId)->whereNull('deleted_at')->where('is_active', true)->count();
        // Coherencia: descenso programado ⇒ el uso cupo (1 sucursal); alta aceptada ⇒ descenso NO programado (2 sucursales).
        $this->assertTrue(
            ($pending === 'basic' && $branches === 1) || ($pending === null && $branches === 2),
            'Estado incoherente descenso/alta: pending='.var_export($pending, true).' sucursales='.$branches
        );
    }

    public function test_descenso_y_apertura_de_caja_concurrentes_se_coordinan(): void
    {
        $bizId = $this->seedBusiness();
        // Cadena SIN límite de cajas (null). Descenso a comercio (3 cajas): se pasa de "sin límite" a un límite.
        $this->seedSubscription($bizId, plan: 'cadena', status: 'active', providerSubId: 'sub_dg_c', paidUntil: now()->addYear());
        $branchId = $this->seedBranch($bizId);
        // 3 cajas YA abiertas (== límite de comercio): el uso actual cabe justo en el destino. Cada sesión abierta la
        // abre un usuario DISTINTO (uniq_open_session_per_user impide dos sesiones abiertas por el mismo usuario).
        for ($i = 1; $i <= 3; $i++) {
            $this->seedOpenCashSession($bizId, $this->seedRegister($bizId, $branchId, 'Caja '.$i), $this->seedUser($bizId));
        }

        // A: DESCENSO cadena→comercio. B: APERTURA de una 4ª caja (usuario nuevo). Mismo lock de cajas: o se programa el
        // descenso y la 4ª apertura se rechaza (cupo del destino), o la 4ª abre y el descenso se rechaza (ya no cabría).
        [$a, $b] = $this->runPair([
            ['scenario' => 'downgrade', 'business_id' => $bizId, 'target_plan' => 'comercio', 'target_period' => 'annual'],
            ['scenario' => 'cash', 'business_id' => $bizId, 'register_id' => $this->seedRegister($bizId, $branchId, 'Caja 4'), 'user_id' => $this->seedUser($bizId)],
        ]);

        $this->assertExactlyOneWinnerAndLimit($a, $b);

        $pending = DB::table('plan_subscriptions')->where('business_id', $bizId)->value('pending_plan_key');
        $open    = DB::table('cash_sessions')->where('business_id', $bizId)->whereIn('status', ['abierta', 'descuadrada'])->count();
        $this->assertTrue(
            ($pending === 'comercio' && $open === 3) || ($pending === null && $open === 4),
            'Estado incoherente descenso/apertura: pending='.var_export($pending, true).' cajas='.$open
        );
    }

    // ============================= Aserciones compartidas =============================

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function assertExactlyOneWinnerAndLimit(array $a, array $b): void
    {
        $oks = array_filter([$a, $b], static fn (array $r): bool => ($r['ok'] ?? false) === true);
        $limited = array_filter([$a, $b], static fn (array $r): bool => ($r['exception'] ?? null) === \App\Exceptions\PlanLimitExceededException::class);

        $this->assertCount(1, $oks, 'Debe haber exactamente un ganador: '.json_encode([$a, $b]));
        $this->assertCount(1, $limited, 'El perdedor debe ser PLAN_LIMIT_EXCEEDED (cupo real), no infra: '.json_encode([$a, $b]));
    }

    // ============================= Fixtures (sin observer; DB::table) =============================

    private function seedBusiness(): int
    {
        return (int) DB::table('businesses')->insertGetId([
            'name' => $this->token.' '.Str::random(5), 'slug' => Str::slug($this->token.'-'.Str::random(5)),
            'plan' => 'basic', 'status' => 'trial', 'timezone' => 'America/Managua',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedUser(int $bizId): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => 'Owner '.Str::random(4), 'email' => Str::random(8).'@subconc.local',
            'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'business_id' => $bizId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedBranch(int $bizId, bool $active = true): int
    {
        return (int) DB::table('branches')->insertGetId([
            'business_id' => $bizId, 'name' => 'Suc '.Str::random(4), 'address' => 'Dir', 'opened_at' => now()->toDateString(),
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedRegister(int $bizId, int $branchId, string $name): int
    {
        return (int) DB::table('cash_registers')->insertGetId([
            'business_id' => $bizId, 'branch_id' => $branchId, 'name' => $name, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedOpenCashSession(int $bizId, int $registerId, int $userId): int
    {
        return (int) DB::table('cash_sessions')->insertGetId([
            'business_id' => $bizId, 'cash_register_id' => $registerId, 'opened_by' => $userId,
            'status' => 'abierta', 'opening_amount' => 0, 'opened_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedSubscription(int $bizId, string $plan, string $status, string $providerSubId, $paidUntil): void
    {
        DB::table('plan_subscriptions')->insert([
            'business_id' => $bizId, 'plan_key' => $plan, 'period' => 'annual', 'status' => $status,
            'provider' => 'lemon_squeezy', 'provider_mode' => 'test', 'store_id' => 'store_conc',
            'provider_subscription_id' => $providerSubId, 'provider_variant_id' => 'var_conc',
            'current_period_start' => $paidUntil === null ? null : now(),
            'paid_until' => $paidUntil, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function renewalPayload(string $subId, string $invId, string $createdAt): array
    {
        return [
            'meta' => ['event_name' => 'subscription_payment_success', 'custom_data' => []],
            'data' => [
                'type' => 'subscription-invoices', 'id' => $invId,
                'attributes' => [
                    'test_mode' => true, 'store_id' => 'store_conc', 'subscription_id' => $subId,
                    'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'renewal',
                    'created_at' => $createdAt, 'updated_at' => $invId,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function paymentPayload(string $subId, string $invId): array
    {
        return [
            'meta' => ['event_name' => 'subscription_payment_success', 'custom_data' => []],
            'data' => [
                'type' => 'subscription-invoices', 'id' => $invId,
                'attributes' => [
                    'test_mode' => true, 'store_id' => 'store_conc', 'subscription_id' => $subId,
                    'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'initial',
                    'updated_at' => '2026-10-06T00:00:00Z',
                ],
            ],
        ];
    }

    // ============================= Infraestructura de la prueba =============================

    /**
     * @param  array<int, array<string, mixed>>  $specs
     * @return array<int, array<string, mixed>>
     */
    private function runPair(array $specs): array
    {
        $procs = [];
        $pipesAll = [];
        foreach ($specs as $slot => $spec) {
            $cfgPath = $this->writeConfig($slot, count($specs), $spec);
            [$proc, $pipes] = $this->spawn($cfgPath);
            $procs[$slot] = $proc;
            $pipesAll[$slot] = $pipes;
        }

        $out = [];
        $err = [];
        foreach ($pipesAll as $slot => $pipes) {
            $out[$slot] = stream_get_contents($pipes[1]);
            $err[$slot] = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
        }
        foreach ($procs as $proc) {
            proc_close($proc);
        }

        $results = [];
        foreach ($specs as $slot => $spec) {
            $decoded = json_decode(trim((string) $out[$slot]), true);
            $this->assertIsArray($decoded, "Salida inválida del worker {$slot}. stdout=[{$out[$slot]}] stderr=[{$err[$slot]}]");
            $this->assertArrayNotHasKey('fatal', $decoded, "Worker {$slot} fatal: ".json_encode($decoded).' stderr=['.$err[$slot].']');
            $results[$slot] = $decoded;
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function writeConfig(int $slot, int $peers, array $spec): string
    {
        $cfg = array_merge([
            'base'    => base_path(),
            'db'      => self::DB_NAME,
            'slot'    => $slot,
            'peers'   => $peers,
            'barrier' => $this->barrierDir,
            'timeout' => 20.0,
        ], $spec);

        $path = $this->barrierDir.DIRECTORY_SEPARATOR.'config.'.$slot.'.json';
        file_put_contents($path, json_encode($cfg));

        return $path;
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function spawn(string $configPath): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = [PHP_BINARY, __DIR__.DIRECTORY_SEPARATOR.'subscription_concurrency_worker.php', $configPath];
        $proc = proc_open($cmd, $descriptors, $pipes, base_path(), null);

        if (! is_resource($proc)) {
            $this->fail('No se pudo lanzar el proceso worker.');
        }
        fclose($pipes[0]);

        return [$proc, $pipes];
    }

    /** @return array<int, int> */
    private function businessIds(): array
    {
        return DB::table('businesses')->where('name', 'like', $this->token.'%')
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    private function cleanupToken(): void
    {
        if ($this->token === '') {
            return;
        }

        try {
            $ids = $this->businessIds();
            if ($ids === []) {
                return;
            }

            DB::table('subscription_payments')->whereIn('business_id', $ids)->delete();
            DB::table('billing_webhook_events')->whereIn('business_id', $ids)->delete();
            DB::table('checkout_intents')->whereIn('business_id', $ids)->delete();
            DB::table('plan_subscriptions')->whereIn('business_id', $ids)->delete();
            DB::table('cash_sessions')->whereIn('business_id', $ids)->delete();
            DB::table('cash_registers')->whereIn('business_id', $ids)->delete();
            DB::table('branches')->whereIn('business_id', $ids)->delete();
            DB::table('users')->whereIn('business_id', $ids)->delete();
            DB::table('businesses')->whereIn('id', $ids)->delete();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Cleanup subscription concurrency: '.$e->getMessage().PHP_EOL);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
