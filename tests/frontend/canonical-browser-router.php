<?php

declare(strict_types=1);

// CLI-server QA only. Real Laravel, gateway, cookies and middleware; no fake payments.
// Isolate Vite's hot pointer without moving or deleting another developer's files.
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && is_file($root.'/public'.$path)) {
    return false;
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
require __DIR__.'/subscription-qa-guard.php';

try {
    if (PHP_SAPI !== 'cli-server' || getenv('QA_CANONICAL_BUILT_ASSETS') !== '1'
        || getenv('QA_REGISTRATION_BROWSER') !== '1') {
        throw new RuntimeException('Explicit canonical QA opt-in required');
    }
    subscriptionQaGuard(); // Effective Laravel config and PDO database, not just localhost.
    $unusedHot = storage_path('framework/canonical-qa-unused-hot');
    if (file_exists($unusedHot)) {
        throw new RuntimeException('Unexpected QA hot pointer; stop');
    }
    $app->make(Illuminate\Foundation\Vite::class)->useHotFile($unusedHot);
} catch (Throwable $error) {
    http_response_code(503);
    echo 'Canonical QA preflight rejected; no request dispatched.';
    return;
}

$app->handleRequest(Illuminate\Http\Request::capture());
