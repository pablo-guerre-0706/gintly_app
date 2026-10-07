<?php
declare(strict_types=1);
// Isolated QA server ONLY: existing provider double, no app bypass, real session/CSRF/policies/gates.
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && is_file($root.'/public'.$path)) return false;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
require __DIR__.'/subscription-qa-guard.php';
try {
    subscriptionQaGuard();
    subscriptionQaProvider();
    if (getenv('QA_BILLING_GATEWAY') === 'fake') $app->instance(App\Contracts\SubscriptionGateway::class, new Tests\Support\FakeSubscriptionGateway());
    elseif (getenv('QA_BILLING_GATEWAY') === 'unavailable') config(['billing.api_key'=>null]);
    else throw new RuntimeException('QA provider mode required');
} catch (Throwable $error) { http_response_code(503); echo 'QA preflight rejected; no request dispatched.'; return; }
$app->handleRequest(Illuminate\Http\Request::capture());
