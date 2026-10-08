<?php
// CLI-only, read-only acceptance guard/evidence. No HTTP route or credentials output.
declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$stage = 'environment';
try {
    if (PHP_SAPI !== 'cli' || getenv('QA_REGISTRATION_BROWSER') !== '1'
        || !in_array(getenv('APP_ENV'), ['local', 'testing'], true)
        || getenv('APP_URL') !== 'http://127.0.0.1:8840'
        || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_HOST') !== '127.0.0.1'
        || getenv('DB_DATABASE') !== 'gintly_frontend_qa_rol03'
        || getenv('DB_URL') || getenv('DB_SOCKET')) {
        throw new RuntimeException('Explicit CLI allowlisted QA opt-in required');
    }
    $root = dirname(__DIR__, 2);
    $stage = 'bootstrap';
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection = DB::connection();
    $config = $connection->getConfig(); // Resolved configuration, including any cached/URL settings.
    $stage = 'effective-configuration';
    if (!in_array($app->environment(), ['local', 'testing'], true)
        || config('app.debug') !== false
        || config('app.url') !== 'http://127.0.0.1:8840'
        || config('database.default') !== 'mysql'
        || ($config['driver'] ?? null) !== 'mysql'
        || ($config['host'] ?? null) !== '127.0.0.1'
        || ($config['database'] ?? null) !== 'gintly_frontend_qa_rol03'
        || !empty($config['url']) || !empty($config['unix_socket'])
        || !empty($config['read']) || !empty($config['write'])
        || config('session.driver') !== 'file' || config('session.connection')
        || config('session.domain') || config('session.secure')
        || config('cache.default') !== 'file'
        || !in_array('127.0.0.1:8840', config('sanctum.stateful', []), true)) {
        throw new RuntimeException('Effective Laravel configuration is not the allowlisted QA destination');
    }
    // Check the database actually selected by PDO, not only an environment label.
    $stage = 'actual-connection';
    $database = $connection->getPdo()->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== 'gintly_frontend_qa_rol03') {
        throw new RuntimeException('Connected database is not allowlisted');
    }
    $stage = 'existing-schema-and-seeds';
    foreach (['businesses', 'users', 'registration_requests', 'roles', 'permissions'] as $table) {
        if (!Schema::hasTable($table)) {
            throw new RuntimeException('Required existing QA schema missing: '.$table);
        }
    }
    if (!DB::table('roles')->where('name', 'ROL-01')->exists()) {
        throw new RuntimeException('Existing QA role seed required');
    }
    $stage = 'fixture-evidence';
    $prefix = $argv[1] ?? 'QA-REGISTER-6defd52506e9';
    if (!preg_match('/^QA-REGISTER-[a-zA-Z0-9-]+$/D', $prefix)) {
        throw new RuntimeException('QA fixture prefix required');
    }
    $fixtures = [];
    foreach (DB::table('businesses')->where('name', 'like', $prefix.'%')->get(['id', 'name']) as $business) {
        $fixtures[] = ['id' => $business->id, 'name' => $business->name,
            'owners' => DB::table('users')->where('business_id', $business->id)->count(),
            'registrations' => DB::table('registration_requests')->where('business_id', $business->id)->count()];
    }
    echo json_encode(['safe_qa' => true, 'environment' => $app->environment(),
        'origin' => config('app.url'), 'database' => $database, 'fixtures' => $fixtures,
        'legacy_rows' => Schema::hasTable('register_wizards') ? DB::table('register_wizards')->count() : 0], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    // SQL/connection exceptions can contain DSNs/secrets: never print their messages.
    fwrite(STDERR, 'QA check failed at '.$stage.'; stop acceptance.'.PHP_EOL);
    exit(1);
}
