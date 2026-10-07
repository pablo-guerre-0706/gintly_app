<?php
declare(strict_types=1);
// Test infrastructure only, never an application route. Checks ENV + resolved Laravel + PDO.
function subscriptionQaGuard(): void
{
    if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true) || getenv('QA_SUBSCRIPTION_BROWSER') !== '1'
        || !in_array(getenv('APP_ENV'), ['local', 'testing'], true) || getenv('APP_URL') !== 'http://127.0.0.1:8840'
        || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_HOST') !== '127.0.0.1'
        || getenv('DB_DATABASE') !== 'gintly_frontend_qa_rol03' || getenv('DB_URL') || getenv('DB_SOCKET')) throw new RuntimeException('Explicit QA environment rejected');
    $connection = Illuminate\Support\Facades\DB::connection(); $config = $connection->getConfig();
    if (!in_array(app()->environment(), ['local', 'testing'], true) || config('app.debug') !== false
        || config('app.url') !== 'http://127.0.0.1:8840' || config('database.default') !== 'mysql'
        || ($config['driver'] ?? null) !== 'mysql' || ($config['host'] ?? null) !== '127.0.0.1'
        || ($config['database'] ?? null) !== 'gintly_frontend_qa_rol03' || !empty($config['url']) || !empty($config['unix_socket'])
        || !empty($config['read']) || !empty($config['write']) || config('session.driver') !== 'file'
        || config('session.connection') || config('session.domain') || config('session.secure')
        || config('cache.default') !== 'file' || !in_array('127.0.0.1:8840', config('sanctum.stateful', []), true)
        || $connection->getPdo()->query('SELECT DATABASE()')->fetchColumn() !== 'gintly_frontend_qa_rol03') throw new RuntimeException('Effective QA destination rejected');
}
function subscriptionQaProvider(): void
{
    config(['billing.deployment_purpose'=>'demo', 'billing.provider_mode'=>'test', 'billing.store_id'=>'qa_store',
        'billing.return_url'=>'http://127.0.0.1:8840/billing/return', 'billing.cancel_url'=>'http://127.0.0.1:8840/billing/return']);
    foreach (array_keys(config('billing.catalog')) as $plan) foreach (config('billing.periods') as $period) config(['billing.variants.test.'.$plan.'.'.$period=>'qa_'.$plan.'_'.$period]);
}
