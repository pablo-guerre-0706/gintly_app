<?php
declare(strict_types=1);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/subscription-qa-guard.php';
try {
    subscriptionQaGuard();
    $action = $argv[1] ?? 'preflight'; $token = $argv[2] ?? '';
    if ($action === 'migrate') {
        foreach (['2026_10_06_000001_create_billing_subscription_tables.php','2026_10_06_000002_add_webhook_recovery_columns.php','2026_10_06_000003_add_expires_at_to_checkout_intents.php','2026_10_06_000004_add_pending_variant_to_plan_subscriptions.php'] as $migration) {
            if (Illuminate\Support\Facades\Artisan::call('migrate', ['--path'=>'database/migrations/'.$migration, '--force'=>true]) !== 0) throw new RuntimeException('Progressive migration rejected');
        }
    }
    foreach (['plan_subscriptions','checkout_intents','billing_webhook_events','subscription_payments'] as $table) if (!Schema::hasTable($table)) throw new RuntimeException('Required MOD-SUB schema missing; run guarded migrate');
    if (!Schema::hasColumn('checkout_intents','expires_at') || !Schema::hasColumn('plan_subscriptions','pending_variant_id') || !Schema::hasColumn('billing_webhook_events','payload')) throw new RuntimeException('Progressive MOD-SUB schema incomplete');
    $business = null;
    if (!in_array($action, ['preflight','migrate'], true)) {
        if (!preg_match('/^QA-REGISTER-MODSUB-[a-f0-9]{12}$/D', $token)) throw new RuntimeException('Exact fixture token required');
        $business = App\Models\Business::withoutGlobalScopes()->where('name', $token)->firstOrFail();
    }
    if ($action === 'activate') {
        subscriptionQaProvider();
        App\Models\PlanSubscription::updateOrCreate(['business_id'=>$business->id], ['plan_key'=>'cadena','period'=>'annual','status'=>'active','provider'=>'lemon_squeezy','provider_mode'=>'test','store_id'=>'qa_store','provider_subscription_id'=>'qa_'.$business->id,'provider_variant_id'=>'qa_cadena_annual','current_period_start'=>now(),'paid_until'=>now()->addMonth(),'renews_at'=>now()->addMonth(),'canceled_at'=>null,'pending_plan_key'=>null,'pending_period'=>null,'pending_variant_id'=>null,'pending_effective_at'=>null]);
    } elseif ($action === 'expire-subscription') {
        DB::table('plan_subscriptions')->where('business_id',$business->id)->update(['status'=>'expired','paid_until'=>now()->subMinute()]);
    } elseif ($action === 'expire-key') {
        DB::table('checkout_intents')->where('business_id',$business->id)->where('status','created')->update(['expires_at'=>now()->subMinute()]);
    } elseif ($action === 'counts') {
        echo json_encode(['businesses'=>1,'users'=>DB::table('users')->where('business_id',$business->id)->count(),'checkouts'=>DB::table('checkout_intents')->where('business_id',$business->id)->count(),'registrations'=>DB::table('registration_requests')->where('business_id',$business->id)->count()], JSON_THROW_ON_ERROR).PHP_EOL; exit;
    } elseif ($action === 'cleanup') {
        DB::transaction(function () use ($business) {
            $id = $business->id; $users = DB::table('users')->where('business_id',$id)->pluck('id');
            // Only this exact new token. No operational documents are produced by this harness.
            foreach (['subscription_payments','billing_webhook_events','checkout_intents','plan_subscriptions','registration_requests','user_operative_profiles','audit_logs'] as $table) DB::table($table)->where('business_id',$id)->delete();
            DB::table('model_has_roles')->where('business_id',$id)->whereIn('model_id',$users)->delete();
            DB::table('model_has_permissions')->where('business_id',$id)->whereIn('model_id',$users)->delete();
            DB::table('users')->where('business_id',$id)->update(['branch_id'=>null]);
            DB::table('branches')->where('business_id',$id)->delete();
            DB::table('businesses')->where('id',$id)->update(['owner_user_id'=>null]);
            DB::table('users')->where('business_id',$id)->delete();
            DB::table('businesses')->where('id',$id)->delete();
        });
    } elseif (!in_array($action, ['preflight','migrate','activate','expire-key','expire-subscription'], true)) throw new RuntimeException('Unknown guarded action');
    echo json_encode(['safe_qa'=>true,'database'=>'gintly_frontend_qa_rol03','action'=>$action,'fixture_id'=>$business?->id], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR, 'MOD-SUB guarded QA action failed ('.$action.'); no secrets printed.'.PHP_EOL); exit(1); }
