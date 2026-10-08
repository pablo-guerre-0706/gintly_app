<?php

declare(strict_types=1);

/**
 * Worker de CONCURRENCIA REAL de MOD-SUB (lo lanza SubscriptionConcurrencyTest con proc_open, un proceso por
 * solicitud, cada uno con su propia conexión MySQL que CONFIRMA de verdad). NO es una prueba PHPUnit (no termina
 * en Test.php) ni simula los índices/locks: ejecuta los SERVICIOS reales (SubscriptionService / PlanLimits) con
 * GET_LOCK y escrituras reales. El proveedor se sustituye por un doble LIMPIO (sin red). La sincronización es una
 * BARRERA de archivos con timeout finito (spin con micro-yield; la corrección proviene de la barrera + los
 * índices UNIQUE + los locks, no de la duración).
 *
 * Entrada: argv[1] = JSON { base, db, slot, peers, barrier, timeout, scenario, business_id, ... }.
 * Salida (stdout, JSON): { slot, ok, ... } o { ok:false, fatal }.
 */

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

$configPath = $argv[1] ?? null;
if ($configPath === null || ! is_file($configPath)) {
    fwrite(STDOUT, json_encode(['ok' => false, 'fatal' => 'no_config']));
    exit(0);
}

/** @var array<string, mixed> $cfg */
$cfg  = json_decode((string) file_get_contents($configPath), true) ?: [];
$slot = (int) ($cfg['slot'] ?? -1);

$fail = static function (string $reason) use ($slot): never {
    fwrite(STDOUT, json_encode(['slot' => $slot, 'ok' => false, 'fatal' => $reason]));
    exit(0);
};

$base = (string) ($cfg['base'] ?? '');
if ($base === '' || ! is_file($base.'/vendor/autoload.php')) {
    $fail('bad_base');
}

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql.database', (string) $cfg['db']);
$app['config']->set('database.connections.mysql.url', null);

if (! $app->environment('testing')) {
    $fail('env_not_testing');
}
if ((string) $cfg['db'] !== 'gintly_backend_claude') {
    $fail('db_not_authorized');
}

// Configuración de cobro COHERENTE (demo/test) para el worker; el proveedor es un doble limpio (sin red).
$app['config']->set('billing.deployment_purpose', 'demo');
$app['config']->set('billing.provider_mode', 'test');
$app['config']->set('billing.store_id', 'store_conc');
$app['config']->set('billing.webhook_secret', 'whsec_conc');
$app['config']->set('billing.lock_timeout_seconds', 10);
// Variantes de prueba por plan/periodo: 'cadena' es la del negocio sembrado; 'basic'/'comercio' son destinos de
// DESCENSO (requestPlanChange resuelve la variante destino vía catálogo). Sin red: el proveedor es un doble limpio.
$app['config']->set('billing.variants.test.cadena.annual', 'var_conc');
$app['config']->set('billing.variants.test.basic.annual', 'var_basic');
$app['config']->set('billing.variants.test.comercio.annual', 'var_comercio');
$app['config']->set('billing.return_url', 'https://app.test/return');

// Doble LIMPIO del proveedor (sin red). Se requiere el archivo explícitamente por si el autoload-dev de Tests\
// no estuviera registrado en el subproceso.
require_once $base.'/tests/Support/FakeSubscriptionGateway.php';
$app->instance(App\Contracts\SubscriptionGateway::class, new Tests\Support\FakeSubscriptionGateway());

$barrier = (string) $cfg['barrier'];
$peers   = (int) $cfg['peers'];
$timeout = (float) $cfg['timeout'];

@file_put_contents($barrier.'/ready.'.$slot, '1');
$deadline = microtime(true) + $timeout;
$allReady = static function () use ($barrier, $peers): bool {
    for ($i = 0; $i < $peers; $i++) {
        if (! is_file($barrier.'/ready.'.$i)) {
            return false;
        }
    }

    return true;
};
while (! $allReady()) {
    if (microtime(true) > $deadline) {
        $fail('barrier_timeout');
    }
    usleep(100);
}

$scenario = (string) ($cfg['scenario'] ?? '');
$bizId    = (int) ($cfg['business_id'] ?? 0);

try {
    switch ($scenario) {
        case 'checkout':
            $business = App\Models\Business::query()->findOrFail($bizId);
            $intent = $app->make(App\Services\Billing\SubscriptionService::class)
                ->checkout($business, 'cadena', 'annual', (string) $cfg['key'], null);
            $out = ['slot' => $slot, 'ok' => true, 'url' => (string) $intent->checkout_url, 'intent_id' => (int) $intent->id];
            break;

        case 'webhook':
            $payload = (array) ($cfg['payload'] ?? []);
            $result = $app->make(App\Services\Billing\SubscriptionService::class)->processVerifiedWebhook($payload);
            $out = ['slot' => $slot, 'ok' => true, 'result' => $result];
            break;

        case 'branch':
            // Inserción vía DB::table (el business_id lo gestiona BelongsToBusiness desde el contexto de sesión,
            // ausente en el worker): se fija explícitamente. El lock + conteo REALES de PlanLimits sí se ejecutan.
            $branchId = $app->make(App\Services\Billing\PlanLimits::class)->guardBranchActivation(
                $bizId,
                static fn (): int => (int) Illuminate\Support\Facades\DB::table('branches')->insertGetId([
                    'business_id' => $bizId,
                    'name'        => 'Suc '.$slot.'-'.bin2hex(random_bytes(3)),
                    'address'     => 'Dir concurrencia',
                    'opened_at'   => date('Y-m-d'),
                    'is_active'   => true,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ])
            );
            $out = ['slot' => $slot, 'ok' => true, 'branch_id' => $branchId];
            break;

        case 'branch_reactivate':
            // Reactiva (is_active false→true) una sucursal existente BAJO la misma guarda de límite: debe competir
            // con las altas concurrentes exactamente igual (cuenta como una activación frente al cupo).
            $branchId = (int) ($cfg['branch_id'] ?? 0);
            $app->make(App\Services\Billing\PlanLimits::class)->guardBranchActivation(
                $bizId,
                static fn (): int => (int) Illuminate\Support\Facades\DB::table('branches')
                    ->where('id', $branchId)->update(['is_active' => true, 'updated_at' => date('Y-m-d H:i:s')])
            );
            $out = ['slot' => $slot, 'ok' => true, 'reactivated' => $branchId];
            break;

        case 'cash':
            $registerId = (int) ($cfg['register_id'] ?? 0);
            $userId     = (int) ($cfg['user_id'] ?? 0);
            $sessionId = $app->make(App\Services\Billing\PlanLimits::class)->guardCashSessionOpen(
                $bizId,
                static fn (): int => (int) Illuminate\Support\Facades\DB::table('cash_sessions')->insertGetId([
                    'business_id'      => $bizId,
                    'cash_register_id' => $registerId,
                    'opened_by'        => $userId,
                    'status'           => 'abierta',
                    'opening_amount'   => 0,
                    'opened_at'        => date('Y-m-d H:i:s'),
                    'created_at'       => date('Y-m-d H:i:s'),
                    'updated_at'       => date('Y-m-d H:i:s'),
                ])
            );
            $out = ['slot' => $slot, 'ok' => true, 'session_id' => $sessionId];
            break;

        case 'downgrade':
            // DESCENSO real (requestPlanChange): bajo los MISMOS locks de límite (sucursales→cajas) comprueba que el
            // uso cabe en el destino y PERSISTE el pendiente. Compite con altas/aperturas concurrentes por ese lock;
            // la red (PATCH de variante) queda fuera y la atiende el doble limpio. No borra recursos.
            $business = App\Models\Business::query()->findOrFail($bizId);
            $sub = $app->make(App\Services\Billing\SubscriptionService::class)->requestPlanChange(
                $business,
                (string) ($cfg['target_plan'] ?? ''),
                (string) ($cfg['target_period'] ?? 'annual')
            );
            $out = ['slot' => $slot, 'ok' => true, 'pending' => (string) $sub->pending_plan_key];
            break;

        default:
            $fail('bad_scenario');
    }
} catch (\Throwable $e) {
    $out = ['slot' => $slot, 'ok' => false, 'exception' => get_class($e), 'message' => $e->getMessage()];
}

fwrite(STDOUT, json_encode($out));
exit(0);
