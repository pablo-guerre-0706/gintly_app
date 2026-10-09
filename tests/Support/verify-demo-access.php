<?php

declare(strict_types=1);
use App\Models\Branch;
use App\Models\Business;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Services\Billing\CommercialAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Read-only local acceptance through the real HTTP Kernel, stored identities and unmodified middleware.
// No password login, cookies, provider requests, operational mutations or production destinations.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $options = getopt('', ['database:', 'demo-id:', 'blocked-id:']);
    foreach (['demo-id', 'blocked-id'] as $key) {
        if (! preg_match('/^[1-9][0-9]*$/D', (string) ($options[$key] ?? ''))) {
            throw new RuntimeException('Missing positive IDs');
        }
    }
    $db = DB::connection();
    $config = $db->getConfig();
    $actual = $db->getPdo()->query('SELECT DATABASE()')->fetchColumn();
    if (! $app->environment('local') || $db->getDriverName() !== 'mysql'
        || ! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true)
        || $actual !== ($options['database'] ?? '') || ! empty($config['url'])
        || ! empty($config['read']) || ! empty($config['write']) || ! empty($config['unix_socket'])) {
        throw new RuntimeException('Local destination does not match expectation');
    }
    $demoId = (int) $options['demo-id'];
    $blockedId = (int) $options['blocked-id'];
    $access = app(CommercialAccess::class);
    $demo = Business::findOrFail($demoId);
    $blocked = Business::findOrFail($blockedId);
    if ($demoId === $blockedId || $demo->slug !== config('billing.demo_access.business_slug')
        || $access->demoGrantFor($demoId) === null || $access->hasPaidAccess($demoId)
        || $access->grantsAccess($blockedId)) {
        throw new RuntimeException('Expected demo and unpaid tenants not available');
    }
    config(['session.driver' => 'array', 'cache.default' => 'array', 'app.debug' => false]);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $cases = [];
    $profiles = [];
    $foreignBranch = Branch::withoutGlobalScopes()->where('business_id', $blockedId)->value('id');
    setPermissionsTeamId($demoId);
    foreach (User::where('business_id', $demoId)->where('is_active', true)->get() as $user) {
        $role = $user->getRoleNames()->first();
        if ($role === 'ROL-01' && (int) $user->id === (int) $demo->owner_user_id) {
            $owner = $user;
            $cases[] = [$user, '/dashboard', 200];
            $cases[] = [$user, '/api/v1/dashboard/kpis', 200];
            $cases[] = [$user, '/api/v1/users/'.$blocked->owner_user_id, 403];
        } elseif ($role === 'ROL-02') {
            $admin = $user;
            $cases[] = [$user, '/dashboard', 200];
            $cases[] = [$user, '/api/v1/dashboard/admin', 200];
            $cases[] = [$user, '/api/v1/branches', 200];
        } elseif ($role === 'ROL-03' && $user->branch_id && $user->profileValues() !== []) {
            $profiles = array_values(array_unique([...$profiles, ...$user->profileValues()]));
            $cases[] = [$user, '/dashboard', 200];
            $cases[] = [$user, '/api/v1/dashboard/operative', 200];
            $cases[] = [$user, '/api/v1/branches', 403];
            if ($user->operativeCan('caja.abrir')) {
                $cases[] = [$user, '/api/v1/cash-sessions/current', 200];
            }
            if ($user->operativeCan('bodegas.ver')) {
                $cases[] = [$user, '/api/v1/warehouses', 200];
                $cases[] = [$user, '/api/v1/stock', 200];
                if ($foreignBranch !== null) {
                    $cases[] = [$user, '/api/v1/warehouses?branch_id='.$foreignBranch, 422];
                }
            }
            if ($user->operativeCan('facturas.ver')) {
                $cases[] = [$user, '/api/v1/invoices', 200];
            }
            $cases[] = [$user, '/api/v1/users', 403];
        }
    }
    if (! isset($owner, $admin) || array_diff(['cajero', 'facturador', 'bodeguero', 'despachador'], $profiles) !== []) {
        throw new RuntimeException('Missing expected existing demo roles/profiles; no fixtures will be invented');
    }
    $otherOwner = User::whereKey($blocked->owner_user_id)->where('business_id', $blockedId)->where('is_active', true)->firstOrFail();
    $cases[] = [$otherOwner, '/api/v1/dashboard/kpis?business_id='.$demoId, 403];
    $cases[] = [$otherOwner, '/api/v1/branches?business_id='.$demoId, 403];
    $cases[] = [$otherOwner, '/dashboard', 403];
    $cases[] = [$owner, '/api/v1/billing/subscription', 200];
    $cases[] = [$otherOwner, '/api/v1/billing/subscription', 200];
    $failures = 0;
    foreach ($cases as [$user, $uri, $expected]) {
        Auth::forgetGuards();
        Auth::guard('web')->setUser($user->fresh());
        $request = Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => $uri === '/dashboard' ? 'text/html' : 'application/json']);
        $response = $kernel->handle($request);
        $json = json_decode($response->getContent(), true);
        $ok = $response->getStatusCode() === $expected;
        if ($user->business_id == $blockedId && $expected === 403 && $uri !== '/dashboard') {
            $ok = $ok && ($json['code'] ?? null) === 'SUBSCRIPTION_REQUIRED';
        }
        if (str_contains($uri, '/billing/subscription')) {
            $state = $json['data'] ?? [];
            $ok = $ok && ($state['grants_access'] ?? null) === ($user->business_id == $demoId);
            if ($user->business_id == $demoId) {
                $ok = $ok && ($state['access_source'] ?? null) === 'demo' && array_key_exists('paid_until', $state) && $state['paid_until'] === null;
            }
        }
        if (str_contains($uri, '/dashboard/operative')) {
            $ok = $ok && (int) ($json['data']['branch_id'] ?? 0) === (int) $user->branch_id;
        }
        if ($uri === '/api/v1/warehouses') {
            foreach ($json['data'] ?? [] as $warehouse) {
                $ok = $ok && (int) $warehouse['branch_id'] === (int) $user->branch_id;
            }
        }
        if ($uri === '/api/v1/stock') {
            foreach ($json['data'] ?? [] as $stock) {
                $ok = $ok && (int) ($stock['warehouse']['branch_id'] ?? 0) === (int) $user->branch_id;
            }
        }
        if (! $ok) {
            $failures++;
        }
        echo json_encode(['user_id' => $user->id, 'business_id' => $user->business_id, 'uri' => $uri,
            'status' => $response->getStatusCode(), 'expected' => $expected, 'code' => $json['code'] ?? null, 'passed' => $ok], JSON_UNESCAPED_SLASHES).PHP_EOL;
        $kernel->terminate($request, $response);
    }
    // Auth remains mandatory even when the deployment has an evaluation grant.
    Auth::forgetGuards();
    $request = Request::create('/api/v1/dashboard/kpis', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $response = $kernel->handle($request);
    $anonymousPassed = $response->getStatusCode() === 401;
    if (! $anonymousPassed) {
        $failures++;
    }
    echo json_encode(['anonymous_status' => $response->getStatusCode(), 'expected' => 401, 'passed' => $anonymousPassed]).PHP_EOL;
    $kernel->terminate($request, $response);
    echo json_encode(['checks' => count($cases) + 1, 'failures' => $failures, 'demo_profiles' => $profiles,
        'demo_subscriptions' => PlanSubscription::where('business_id', $demoId)->count()]).PHP_EOL;
    exit($failures === 0 ? 0 : 1);
} catch (Throwable $error) {
    echo 'VERIFY_DEMO_FAILED: '.get_class($error).'. Destination/fixtures must be checked; no operational writes performed.'.PHP_EOL;
    exit(1);
}
