<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Exceptions\SubscriptionRequiredException;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Resources\SubscriptionStatusResource;
use App\Models\DemoAccessGrant;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Services\Billing\BillingCatalog;
use App\Services\Billing\CommercialAccess;
use App\Services\Billing\RegistrationEvaluationGrant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DemoAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No real databases, full seeders, provider calls or globally disabled middleware.
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
        config(['billing.demo_access.enabled' => true, 'billing.demo_access.business_slug' => 'gintly-demo',
            'billing.demo_access.multiple_businesses' => false, 'billing.demo_access.registration.enabled' => false,
            'billing.deployment_purpose' => 'demo', 'billing.provider_mode' => 'test', 'billing.store_id' => 'qa-test']);
        Carbon::setTestNow(Carbon::parse('2026-10-08 12:00:00', 'UTC'));
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('businesses')->insert([
            ['id' => 1, 'slug' => 'gintly-demo', 'status' => 'trial'],
            ['id' => 2, 'slug' => 'not-selected', 'status' => 'active'],
        ]);
        Schema::create('plan_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            foreach (['plan_key', 'period', 'status', 'provider', 'provider_mode', 'store_id', 'pending_plan_key', 'pending_period'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['paid_until', 'renews_at', 'canceled_at', 'pending_effective_at'] as $field) {
                $table->timestamp($field)->nullable();
            }
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_08_000001_create_demo_access_grants_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function command(string $action = 'grant', array $options = [], int $exit = 0): void
    {
        $this->artisan('billing:demo-access', $options + ['action' => $action, 'business' => 'gintly-demo',
            '--database' => ':memory:', '--business-id' => '1', '--days' => '7', '--plan' => 'cadena',
            '--reason' => 'QA explicit evaluation'])->assertExitCode($exit);
    }

    public function test_grant_uses_catalog_and_only_the_selected_business_without_subscription(): void
    {
        $this->command();
        $access = app(CommercialAccess::class);
        $this->assertTrue($access->grantsAccess(1));
        $this->assertFalse($access->grantsAccess(2));
        $this->assertSame('cadena', $access->activePlanKey(1));
        $this->assertTrue(app(BillingCatalog::class)->planHasFeature($access->activePlanKey(1), 'inventory'));
        $this->assertSame(5, app(BillingCatalog::class)->limit($access->activePlanKey(1), 'branches'));
        $this->assertNull(app(BillingCatalog::class)->limit($access->activePlanKey(1), 'cash_sessions'));
        $this->assertSame(0, PlanSubscription::count());
        $this->assertSame(1, DemoAccessGrant::count());
        $this->assertSame('2026-10-15 12:00:00', DemoAccessGrant::first()->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_revoke_is_idempotent_and_retains_the_record(): void
    {
        $this->command();
        $this->command('revoke');
        $before = DemoAccessGrant::first()->getAttributes();
        $this->command('revoke');
        $this->command('status');
        $this->assertSame($before, DemoAccessGrant::first()->getAttributes());
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(1));
        $this->assertSame(1, DemoAccessGrant::count());
        $this->assertSame(0, PlanSubscription::count());
    }

    public function test_only_explicit_grant_renews_expiry_without_duplicate_rows(): void
    {
        $this->command();
        Carbon::setTestNow(now()->addDays(2));
        $this->command();
        $this->assertSame(1, DemoAccessGrant::count());
        $this->assertSame('2026-10-17 12:00:00', DemoAccessGrant::first()->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_expiry_is_exclusive_and_no_access_before_start(): void
    {
        $this->command();
        $access = app(CommercialAccess::class);
        $this->assertFalse($access->grantsAccess(1, Carbon::parse('2026-10-08 11:59:59')));
        $this->assertTrue($access->grantsAccess(1, Carbon::parse('2026-10-15 11:59:59')));
        $this->assertFalse($access->grantsAccess(1, Carbon::parse('2026-10-15 12:00:00')));
    }

    public static function invalidConfiguration(): array
    {
        return [
            'disabled' => [['billing.demo_access.enabled' => false]],
            'different selection' => [['billing.demo_access.business_slug' => 'not-selected']],
            'no selection' => [['billing.demo_access.business_slug' => '']],
            'commercial live' => [['billing.deployment_purpose' => 'commercial', 'billing.provider_mode' => 'live']],
            'demo live incoherent' => [['billing.provider_mode' => 'live']],
        ];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_configuration_cannot_bypass_the_gate(array $configuration): void
    {
        $this->command();
        config($configuration);
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(1));
        $this->command('grant', [], 1);
        // Revocation must still work after disabling or changing deployment configuration.
        $this->command('revoke');
        $this->assertNotNull(DemoAccessGrant::first()->revoked_at);
    }

    public static function invalidArguments(): array
    {
        return [
            'wrong database' => [['--database' => 'another_database']],
            'wrong tenant' => [['--business-id' => '2']],
            'wrong slug' => [['business' => 'not-selected']],
            'zero days' => [['--days' => '0']],
            'too long' => [['--days' => '31']],
            'fractional days' => [['--days' => '1.5']],
            'invented plan' => [['--plan' => 'unlimited']],
            'no reason' => [['--reason' => '']],
        ];
    }

    #[DataProvider('invalidArguments')]
    public function test_rejected_grant_writes_nothing(array $options): void
    {
        $this->command('grant', $options, 1);
        $this->assertSame(0, DemoAccessGrant::count());
        $this->assertSame(0, PlanSubscription::count());
    }

    public function test_suspended_business_cannot_use_or_renew_demo(): void
    {
        $this->command();
        DB::table('businesses')->where('id', 1)->update(['status' => 'suspended']);
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(1));
        $this->command('grant', [], 1);
    }

    public function test_paid_access_takes_precedence_and_survives_revocation(): void
    {
        $this->command();
        // SQLite-only fixture: never creates a payment or calls a gateway in the real database.
        PlanSubscription::create(['business_id' => 1, 'plan_key' => 'basic', 'period' => 'monthly',
            'status' => 'active', 'provider' => 'lemon_squeezy', 'provider_mode' => 'test',
            'store_id' => 'qa-test', 'paid_until' => now()->addDay()]);
        $this->assertTrue(app(CommercialAccess::class)->hasPaidAccess(1));
        $this->assertSame('basic', app(CommercialAccess::class)->activePlanKey(1));
        $this->command('revoke');
        $this->assertTrue(app(CommercialAccess::class)->grantsAccess(1));
    }

    public function test_effective_demo_response_never_claims_paid_subscription(): void
    {
        $this->command();
        $access = app(CommercialAccess::class);
        $state = (new SubscriptionStatusResource(null, $access->demoGrantFor(1), $access->grantsAccess(1)))->resolve();
        $this->assertSame('none', $state['status']);
        $this->assertTrue($state['grants_access']);
        $this->assertSame('demo', $state['access_source']);
        $this->assertSame('cadena', $state['demo_access']['plan_key']);
        $this->assertNull($state['paid_until']);
        $this->assertNull($state['plan_key']);
        $this->assertNull($state['period']);
    }

    public function test_middleware_uses_authenticated_tenant_not_query_parameters(): void
    {
        $this->command();
        $gate = app(EnsureActiveSubscription::class);
        $request = Request::create('/api/v1/branches?business_id=2');
        $request->setUserResolver(fn () => (new User)->forceFill(['business_id' => 1]));
        $this->assertSame('allowed', $gate->handle($request, fn () => response('allowed'))->getContent());
        $request = Request::create('/api/v1/branches?business_id=1');
        $request->setUserResolver(fn () => (new User)->forceFill(['business_id' => 2]));
        $this->expectException(SubscriptionRequiredException::class);
        $gate->handle($request, fn () => response('must never execute'));
    }

    private function enrollment(array $overrides = []): void
    {
        config(array_merge([
            'billing.demo_access.multiple_businesses' => true,
            'billing.demo_access.registration.enabled' => true,
            'billing.demo_access.registration.starts_at' => '2026-10-08T12:00:00Z',
            'billing.demo_access.registration.ends_at' => '2026-10-09T12:00:00Z',
            'billing.demo_access.registration.days' => 7,
            'billing.demo_access.registration.plan' => 'comercio',
        ], $overrides));
    }

    public function test_multiple_mode_still_requires_each_explicit_grant_and_preserves_demo(): void
    {
        $this->command();
        config(['billing.demo_access.multiple_businesses' => true]);
        $this->assertTrue(app(CommercialAccess::class)->grantsAccess(1));
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(2));
        $this->command('grant', ['business' => 'not-selected', '--business-id' => '2']);
        $this->assertTrue(app(CommercialAccess::class)->grantsAccess(2));
        $this->assertSame(2, DemoAccessGrant::count());
        $this->assertSame(0, PlanSubscription::count());
    }

    public function test_atomic_administrative_batch_grants_and_revokes_each_business(): void
    {
        config(['billing.demo_access.multiple_businesses' => true]);
        $this->command('grant', ['--target' => ['not-selected:2']]);
        $this->assertSame(2, DemoAccessGrant::count());
        $this->command('revoke', ['--target' => ['not-selected:2']]);
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(1));
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(2));
        $this->assertSame(2, DemoAccessGrant::whereNotNull('revoked_at')->count());
    }

    public function test_invalid_or_suspended_batch_target_leaves_all_grants_untouched(): void
    {
        config(['billing.demo_access.multiple_businesses' => true]);
        foreach ([['not-selected:999'], ['gintly-demo:1'], ['not-selected:2', 'not-selected:2'], ['bad']] as $targets) {
            $this->command('grant', ['--target' => $targets], 1);
            $this->assertSame(0, DemoAccessGrant::count());
        }
        DB::table('businesses')->where('id', 2)->update(['status' => 'suspended']);
        $this->command('grant', ['--target' => ['not-selected:2']], 1);
        $this->assertSame(0, DemoAccessGrant::count());
    }

    public function test_enrollment_closes_without_revoking_or_extending_individual_expiry(): void
    {
        $this->enrollment();
        $issuer = app(RegistrationEvaluationGrant::class);
        $first = $issuer->grantToNewBusiness(\App\Models\Business::findOrFail(1));
        $this->assertSame('comercio', $first->plan_key);
        $this->assertSame('2026-10-15 12:00:00', $first->expires_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', 'UTC'));
        $this->assertNull($issuer->grantToNewBusiness(\App\Models\Business::findOrFail(2)));
        $this->assertTrue(app(CommercialAccess::class)->grantsAccess(1));
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(2));
        config(['billing.demo_access.registration.enabled' => false]);
        $this->assertTrue(app(CommercialAccess::class)->grantsAccess(1));
        Carbon::setTestNow($first->expires_at);
        $this->assertFalse(app(CommercialAccess::class)->grantsAccess(1));
    }

    public function test_enrollment_before_start_and_when_disabled_uses_normal_contracting(): void
    {
        $this->enrollment(['billing.demo_access.registration.starts_at' => '2026-10-08T12:00:01Z']);
        $issuer = app(RegistrationEvaluationGrant::class);
        $this->assertNull($issuer->grantToNewBusiness(\App\Models\Business::findOrFail(1)));
        config(['billing.demo_access.registration.enabled' => false]);
        $this->assertNull($issuer->grantToNewBusiness(\App\Models\Business::findOrFail(2)));
        $this->assertSame(0, DemoAccessGrant::count());
    }

    public static function invalidEnrollment(): array
    {
        return [
            'master off' => [['billing.demo_access.enabled' => false]],
            'multi off' => [['billing.demo_access.multiple_businesses' => false]],
            'live mode' => [['billing.provider_mode' => 'live']],
            'no timezone' => [['billing.demo_access.registration.starts_at' => '2026-10-08 12:00:00']],
            'invalid date' => [['billing.demo_access.registration.starts_at' => '2026-02-30T12:00:00Z']],
            'inverted window' => [['billing.demo_access.registration.ends_at' => '2026-10-08T12:00:00Z']],
            'zero days' => [['billing.demo_access.registration.days' => 0]],
            'too long' => [['billing.demo_access.registration.days' => 31]],
            'fractional' => [['billing.demo_access.registration.days' => '1.5']],
            'no plan' => [['billing.demo_access.registration.plan' => 'invented']],
        ];
    }

    #[DataProvider('invalidEnrollment')]
    public function test_invalid_enabled_enrollment_fails_closed_without_grant(array $config): void
    {
        $this->enrollment($config);
        try {
            app(RegistrationEvaluationGrant::class)->grantToNewBusiness(\App\Models\Business::findOrFail(1));
            $this->fail('Invalid enrollment must not succeed silently');
        } catch (\LogicException) {
            $this->assertSame(0, DemoAccessGrant::count());
        }
    }

    public function test_registration_rollback_removes_grant_and_duplicate_does_not_renew(): void
    {
        $this->enrollment();
        $business = \App\Models\Business::findOrFail(1);
        try {
            DB::transaction(function () use ($business): void {
                app(RegistrationEvaluationGrant::class)->grantToNewBusiness($business);
                throw new \RuntimeException('Simulate a later registration failure');
            });
        } catch (\RuntimeException) {
            $this->assertSame(0, DemoAccessGrant::count());
        }
        $grant = app(RegistrationEvaluationGrant::class)->grantToNewBusiness($business);
        $before = $grant->getAttributes();
        try {
            app(RegistrationEvaluationGrant::class)->grantToNewBusiness($business);
            $this->fail('Unique per-business entitlement must not duplicate');
        } catch (\Illuminate\Database\QueryException) {
            $after = $grant->fresh()->getAttributes();
            ksort($before);
            ksort($after);
            $this->assertSame($before, $after);
        }
    }
}
