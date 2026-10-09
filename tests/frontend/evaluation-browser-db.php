<?php

declare(strict_types=1);

// CLI-only, exact disposable QA fixtures; never a public configuration/activation endpoint.
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/subscription-qa-guard.php';
$stage = 'guard';
try {
    if (PHP_SAPI !== 'cli' || getenv('QA_EVALUATION_BROWSER') !== '1') {
        throw new RuntimeException('Explicit evaluation QA opt-in required');
    }
    subscriptionQaGuard();
    $action = $argv[1] ?? 'preflight';
    $run = $argv[2] ?? '';
    if (! preg_match('/^QA-REGISTER-EVAL-[a-f0-9]{12}$/D', $run)) {
        throw new RuntimeException('Exact evaluation token required');
    }
    $stage = 'existing-schema';
    if ($action === 'migrate') {
        // Only the existing progressive demo migration, after effective PDO/QA guards above.
        if (Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_08_000001_create_demo_access_grants_table.php', '--force' => true]) !== 0) {
            throw new RuntimeException('Progressive demo migration rejected');
        }
    }
    foreach (['demo_access_grants', 'registration_requests', 'plan_subscriptions', 'checkout_intents', 'subscription_payments'] as $table) {
        $stage = 'schema-'.$table;
        if (! Schema::hasTable($table)) {
            throw new RuntimeException('Existing QA schema required');
        }
    }
    $stage = 'fixtures';
    $names = array_map(fn ($suffix) => $run.'-'.$suffix, ['a', 'b', 'c', 'd']);
    $businesses = App\Models\Business::withoutGlobalScopes()->whereIn('name', $names)->orderBy('id')->get();
    $rows = [];
    foreach ($businesses as $business) {
        $grant = App\Models\DemoAccessGrant::where('business_id', $business->id)->first();
        $rows[] = ['name' => $business->name, 'id' => $business->id, 'owner_id' => $business->owner_user_id,
            'slug' => $business->slug, 'users' => DB::table('users')->where('business_id', $business->id)->count(),
            'registrations' => DB::table('registration_requests')->where('business_id', $business->id)->count(),
            'grant' => $grant ? ['id' => $grant->id, 'starts_at' => $grant->starts_at->toIso8601String(),
                'expires_at' => $grant->expires_at->toIso8601String(), 'revoked_at' => $grant->revoked_at?->toIso8601String()] : null];
    }
    $demo = DB::table('demo_access_grants')->where('business_id', 1)->first();
    $demoFingerprint = hash('sha256', json_encode($demo, JSON_THROW_ON_ERROR));
    if (in_array($action, ['batch-grant', 'batch-revoke'], true)) {
        $selected = $businesses->filter(fn ($business) => in_array($business->name, [$run.'-a', $run.'-b'], true))->values();
        if ($selected->count() !== 2) {
            throw new RuntimeException('Two exact fixtures required');
        }
        $stage = 'administrative-command';
        $options = ['action' => $action === 'batch-grant' ? 'grant' : 'revoke', 'business' => $selected[0]->slug,
            '--database' => 'gintly_frontend_qa_rol03', '--business-id' => (string) $selected[0]->id,
            '--target' => [$selected[1]->slug.':'.$selected[1]->id], '--days' => '7', '--plan' => 'cadena', '--reason' => $run.' CLI QA'];
        if (Artisan::call('billing:demo-access', $options) !== 0) {
            throw new RuntimeException('Administrative command rejected');
        }
    } elseif ($action === 'expire') {
        $stage = 'expiry';
        $business = $businesses->firstWhere('name', $run.'-a');
        if (! $business || ! App\Models\DemoAccessGrant::where('business_id', $business->id)->exists()) {
            throw new RuntimeException('Exact own grant required');
        }
        // Exercise the exclusive server expiry boundary, not a middleware bypass or fake paid state.
        DB::table('demo_access_grants')->where('business_id', $business->id)->update(['expires_at' => now()]);
    } elseif ($action === 'cleanup') {
        $stage = 'cleanup';
        DB::transaction(function () use ($businesses): void {
            foreach ($businesses as $business) {
                $id = $business->id;
                foreach (['plan_subscriptions', 'checkout_intents', 'subscription_payments'] as $table) {
                    if (DB::table($table)->where('business_id', $id)->exists()) {
                        throw new RuntimeException('Unexpected commercial evidence: retain fixture');
                    }
                }
                // Only freshly registered exact tokens, with no operational documents.
                $users = DB::table('users')->where('business_id', $id)->pluck('id');
                foreach (['demo_access_grants', 'registration_requests', 'user_operative_profiles', 'audit_logs'] as $table) {
                    DB::table($table)->where('business_id', $id)->delete();
                }
                DB::table('model_has_roles')->where('business_id', $id)->whereIn('model_id', $users)->delete();
                DB::table('model_has_permissions')->where('business_id', $id)->whereIn('model_id', $users)->delete();
                DB::table('businesses')->where('id', $id)->update(['owner_user_id' => null]);
                DB::table('users')->where('business_id', $id)->delete();
                DB::table('businesses')->where('id', $id)->delete();
            }
        });
    } elseif (! in_array($action, ['preflight', 'inspect', 'migrate'], true)) {
        throw new RuntimeException('Unknown QA operation');
    }
    $ids = $businesses->pluck('id');
    $commercialRows = [];
    foreach (['plan_subscriptions', 'checkout_intents', 'subscription_payments'] as $table) {
        $commercialRows[$table] = DB::table($table)->whereIn('business_id', $ids)->count();
    }
    $orphanUsers = DB::table('users')->where('email', 'like', strtolower($run).'-%@example.test')
        ->whereNotIn('business_id', $ids)->count();
    $orphanRegistrations = DB::table('registration_requests')->where('owner_email', 'like', strtolower($run).'-%@example.test')
        ->whereNotIn('business_id', $ids)->count();
    echo json_encode(['safe_qa' => true, 'database' => 'gintly_frontend_qa_rol03', 'action' => $action,
        'fixtures' => $rows, 'commercial_rows' => $commercialRows, 'demo_fingerprint' => $demoFingerprint,
        'remaining_fixture_count' => DB::table('businesses')->whereIn('name', $names)->count(),
        'orphan_users' => $orphanUsers, 'orphan_registrations' => $orphanRegistrations], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Evaluation QA rejected at '.$stage.'; no secrets printed.'.PHP_EOL);
    exit(1);
}
