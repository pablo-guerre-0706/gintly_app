<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Exceptions\RegistrationIdempotencyConflictException;
use App\Exceptions\RegistrationLockUnavailableException;
use App\Services\Auth\RegistrationService;
use App\Support\RegistrationFailureReporter;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class RegistrationDiagnosticsTest extends TestCase
{
    private const KEY = '8219610b-411b-41c5-b4a2-581baeb12b0c';

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        // No MySQL harness, migrations, real tenants or disabled middleware.
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME));
        config(['app.debug' => false]);
        $this->logs = new TestHandler();
        // Exercise the real isolated Monolog writer, replacing only its physical destination.
        $reporter = new class($this->logs) extends RegistrationFailureReporter {
            public function __construct(private readonly HandlerInterface $handler) {}

            protected function createHandler(): HandlerInterface
            {
                return $this->handler;
            }
        };
        $this->app->instance(RegistrationFailureReporter::class, $reporter);
    }

    public static function unavailableResults(): array
    {
        return [[0, 'timeout'], [null, 'null_result'], [2, 'unexpected_result']];
    }

    #[DataProvider('unavailableResults')]
    public function test_unavailable_lock_is_distinguished_and_prevents_writes($value, string $reason): void
    {
        DB::shouldReceive('select')->once()->with(
            'SELECT GET_LOCK(?, ?) AS locked', ['gintly_reg_'.self::KEY, 10], false,
        )->andReturn([(object) ['locked' => $value]]);
        $error = $this->failedRegistration();
        $this->assertSame($reason, $error->reason);
        $this->assertNull($error->getPrevious());
        app(ExceptionHandler::class)->report($error);
        $records = $this->logs->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame($reason, $records[0]->context['reason']);
        $this->assertSame($error->diagnosticId, $records[0]->context['diagnostic_id']);
    }

    public function test_database_exception_preserves_cause_but_not_sql_secrets_or_request_data_in_log(): void
    {
        $pdo = new PDOException('credential-hidden database-hidden password-hidden');
        $pdo->errorInfo = ['HY000', 2026, 'certificate-hidden'];
        $sql = new QueryException('connection-hidden', 'SELECT secret-hidden', ['binding-hidden'], $pdo);
        DB::shouldReceive('select')->once()->andThrow($sql);
        $error = $this->failedRegistration();
        $this->assertSame($sql, $error->getPrevious());
        app(ExceptionHandler::class)->report($error);
        $records = $this->logs->getRecords();
        $this->assertCount(1, $records);
        $context = $records[0]->context;
        $this->assertSame('HY000', $context['sqlstate']);
        $this->assertSame(2026, $context['driver_code']);
        $this->assertSame('database_tls_failed', $context['category']);
        $this->assertSame(PDOException::class, $context['cause_class']);
        $serialized = (new JsonFormatter())->format($records[0]);
        foreach (['credential-hidden', 'database-hidden', 'password-hidden', 'certificate-hidden',
            'secret-hidden', 'binding-hidden', 'connection-hidden', self::KEY, 'owner@example.test',
            'Sensitive-password-not-for-logs', 'GET_LOCK', 'fingerprint', '"exception":', '"trace":'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
    }

    public function test_500_body_is_unchanged_and_header_correlates_with_one_sanitized_report(): void
    {
        $error = new RegistrationLockUnavailableException(reason: 'timeout', waitSeconds: 10);
        app(ExceptionHandler::class)->report($error);
        $response = app(ExceptionHandler::class)->render(Request::create('/api/v1/auth/register', 'POST'), $error);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['message' => 'No se pudo completar el registro por indisponibilidad temporal. Reintente con la misma Idempotency-Key.'],
            json_decode($response->getContent(), true));
        $this->assertSame($error->diagnosticId, $response->headers->get('X-Registration-Diagnostic-ID'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $error->diagnosticId);
        $this->assertCount(1, $this->logs->getRecords());
        $this->assertSame($response->headers->get('X-Registration-Diagnostic-ID'),
            $this->logs->getRecords()[0]->context['diagnostic_id']);
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_replay_still_returns_same_public_result_and_uses_write_connection_for_both_locks(): void
    {
        $service = app(RegistrationService::class);
        $this->seedReplay($service);
        DB::partialMock()->shouldReceive('select')->once()->with('SELECT GET_LOCK(?, ?) AS locked', ['gintly_reg_'.self::KEY, 10], false)
            ->andReturn([(object) ['locked' => '1']]);
        DB::shouldReceive('select')->once()->with('SELECT RELEASE_LOCK(?)', ['gintly_reg_'.self::KEY], false)->andReturn([]);
        $this->assertSame(['business_slug' => 'qa-readonly', 'owner_email' => 'owner@example.test'],
            $service->register($this->payload(), self::KEY));
        $this->assertSame(1, \App\Models\RegistrationRequest::count());
        $this->assertCount(0, $this->logs->getRecords());
    }

    public function test_logging_channel_uses_container_stderr_without_changing_global_channel(): void
    {
        $this->assertSame('registration', config('logging.channels.registration.name'));
        $this->assertSame('php://stderr', config('logging.channels.registration.handler_with.stream'));
        $this->assertSame(JsonFormatter::class, config('logging.channels.registration.formatter'));
        $this->assertSame('error', config('logging.channels.registration.level'));
        $this->assertSame('stack', config('logging.default'));
    }

    public function test_database_url_is_resolved_but_not_logged(): void
    {
        config(['database.connections.sqlite.url' => 'mysql://private-user:private-password@private-host/private-database']);
        app(RegistrationFailureReporter::class)->record(new RuntimeException('private-message'), 'acquire_lock');
        $context = $this->logs->getRecords()[0]->context;
        $this->assertSame('mysql', $context['effective_driver']);
        $this->assertTrue($context['db_url_present']);
        foreach (['private-user', 'private-password', 'private-host', 'private-database', 'private-message'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($context));
        }
    }

    public function test_untrusted_error_info_and_anonymous_exception_are_not_copied_to_log(): void
    {
        $error = new class('private-message') extends PDOException {};
        $error->errorInfo = ['invalid-state-password-hidden', 'secret-not-a-code', 'private-message'];
        app(RegistrationFailureReporter::class)->record($error, 'acquire_lock');
        $context = $this->logs->getRecords()[0]->context;
        $this->assertNull($context['sqlstate']);
        $this->assertNull($context['driver_code']);
        $this->assertSame('Throwable', $context['exception_class']);
        foreach (['invalid-state-password-hidden', 'secret-not-a-code', 'private-message'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($context));
        }
    }

    public function test_pdo_connection_failure_without_error_info_retains_only_safe_numeric_code(): void
    {
        app(RegistrationFailureReporter::class)->record(new PDOException('private-user-password-dsn', 2002), 'acquire_lock');
        $context = $this->logs->getRecords()[0]->context;
        $this->assertSame('2002', $context['exception_code']);
        $this->assertSame(2002, $context['driver_code']);
        $this->assertSame('database_connection_failed', $context['category']);
        $this->assertStringNotContainsString('private-user-password-dsn', json_encode($context));
    }

    public function test_release_failure_is_logged_without_escaping_or_exposing_raw_cause(): void
    {
        DB::shouldReceive('select')->with('SELECT RELEASE_LOCK(?)', ['gintly_reg_'.self::KEY], false)
            ->once()->andThrow(new RuntimeException('untrusted-sensitive-message'));
        $service = app(RegistrationService::class);
        (new \ReflectionMethod($service, 'releaseLock'))->invoke($service, 'gintly_reg_'.self::KEY);
        $records = $this->logs->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('release_lock', $records[0]->context['stage']);
        $this->assertStringNotContainsString('untrusted-sensitive-message', json_encode($records[0]->context));
    }

    public function test_diagnostic_does_not_inherit_shared_context_request_context_or_logging_listeners(): void
    {
        $secrets = ['password' => 'context-password-hidden', 'cookie' => 'context-cookie-hidden',
            'owner_email' => 'context-owner-hidden@example.test',
            'registration_payload' => ['owner' => ['first_name' => 'context-person-hidden']]];
        $globalLogs = new TestHandler(Level::Debug, false);
        Log::channel('registration')->pushHandler($globalLogs);
        Log::shareContext($secrets);
        Log::channel('registration')->withContext($secrets);
        Context::add($secrets);
        $loggingEvents = 0;
        $this->app['events']->listen(\Illuminate\Log\Events\MessageLogged::class,
            function () use (&$loggingEvents): void { $loggingEvents++; });

        $error = new RegistrationLockUnavailableException(previous: new RuntimeException('raw-password-hidden'));
        app(ExceptionHandler::class)->report($error);
        $record = $this->logs->getRecords()[0];
        $this->assertSame('registration.infrastructure_failure', $record->message);
        $this->assertSame($error->diagnosticId, $record->context['diagnostic_id']);
        $this->assertSame([], $record->extra);
        $serialized = (new JsonFormatter())->format($record);
        foreach (['context-password-hidden', 'context-cookie-hidden', 'context-owner-hidden',
            'context-person-hidden', 'registration_payload', 'raw-password-hidden'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $this->assertCount(0, $globalLogs->getRecords());
        $this->assertSame(0, $loggingEvents);
        // Isolation must not clear another module's logging state.
        $this->assertSame($secrets, Log::sharedContext());
        $this->assertSame($secrets, Context::all());
    }

    public function test_primary_logger_failure_uses_sanitized_fallback_with_same_response_id(): void
    {
        $reporter = $this->failingReporter();
        $error = new RegistrationLockUnavailableException(previous: new RuntimeException('raw-cause-hidden'));
        app(ExceptionHandler::class)->report($error);
        $response = app(ExceptionHandler::class)->render(Request::create('/api/v1/auth/register', 'POST'), $error);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertCount(1, $reporter->fallbackRecords);
        $context = $reporter->fallbackRecords[0];
        $this->assertSame($response->headers->get('X-Registration-Diagnostic-ID'), $context['diagnostic_id']);
        foreach (['logger-secret-hidden', 'raw-cause-hidden', '"exception":', '"trace":', self::KEY] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($context));
        }
        $this->assertCount(0, $this->logs->getRecords());
    }

    public function test_both_log_sinks_failing_do_not_replace_original_response_or_report_raw_exception(): void
    {
        $reporter = $this->failingReporter(fallbackFails: true);
        $error = new RegistrationLockUnavailableException(previous: new RuntimeException('raw-cause-hidden'));
        // Any attempt by Laravel to fall through to its normal raw-exception logger fails this test.
        Log::shouldReceive('error')->never();
        app(ExceptionHandler::class)->report($error);
        $response = app(ExceptionHandler::class)->render(Request::create('/api/v1/auth/register', 'POST'), $error);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['message' => $error->getMessage()], json_decode($response->getContent(), true));
        $this->assertSame($error->diagnosticId, $response->headers->get('X-Registration-Diagnostic-ID'));
        $this->assertCount(1, $reporter->fallbackRecords);
        $this->assertStringNotContainsString('fallback-secret-hidden', $response->getContent());
    }

    public function test_metadata_failure_preserves_minimal_correlation_without_logging_the_failure(): void
    {
        $reporter = $this->failingReporter();
        $cause = new PDOException('raw-cause-hidden');
        $cause->errorInfo = [new class {
            public function __toString(): string { throw new RuntimeException('metadata-secret-hidden'); }
        }];
        $error = new RegistrationLockUnavailableException(previous: $cause);
        app(ExceptionHandler::class)->report($error);
        $this->assertSame(['diagnostic_id' => $error->diagnosticId, 'endpoint' => '/api/v1/auth/register',
            'stage' => 'acquire_lock'], $reporter->fallbackRecords[0]);
    }

    public function test_release_and_logging_failures_do_not_replace_confirmed_replay_or_create_rows(): void
    {
        $service = app(RegistrationService::class);
        $this->seedReplay($service);
        $reporter = $this->failingReporter(fallbackFails: true);
        DB::partialMock()->shouldReceive('select')->once()
            ->with('SELECT GET_LOCK(?, ?) AS locked', ['gintly_reg_'.self::KEY, 10], false)
            ->andReturn([(object) ['locked' => 1]]);
        DB::shouldReceive('select')->once()->with('SELECT RELEASE_LOCK(?)', ['gintly_reg_'.self::KEY], false)
            ->andThrow(new RuntimeException('release-secret-hidden'));

        $this->assertSame(['business_slug' => 'qa-readonly', 'owner_email' => 'owner@example.test'],
            $service->register($this->payload(), self::KEY));
        $this->assertSame(1, \App\Models\RegistrationRequest::count());
        $this->assertCount(1, $reporter->fallbackRecords);
        $this->assertSame('release_lock', $reporter->fallbackRecords[0]['stage']);
    }

    public static function conflictReleaseResults(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('conflictReleaseResults')]
    public function test_divergent_replay_keeps_409_and_releases_lock_even_if_release_and_logging_fail(bool $releaseFails): void
    {
        $service = app(RegistrationService::class);
        $this->seedReplay($service);
        $this->failingReporter(fallbackFails: true);
        DB::partialMock()->shouldReceive('select')->once()
            ->with('SELECT GET_LOCK(?, ?) AS locked', ['gintly_reg_'.self::KEY, 10], false)
            ->andReturn([(object) ['locked' => 1]]);
        $release = DB::shouldReceive('select')->once()
            ->with('SELECT RELEASE_LOCK(?)', ['gintly_reg_'.self::KEY], false);
        $releaseFails ? $release->andThrow(new RuntimeException('release-secret-hidden')) : $release->andReturn([]);
        $changed = $this->payload();
        $changed['business']['name'] = 'QA Different';
        try {
            $service->register($changed, self::KEY);
            $this->fail('A different payload must not reuse the recorded registration.');
        } catch (RegistrationIdempotencyConflictException $error) {
            $response = $error->render();
            $this->assertSame(409, $response->getStatusCode());
            $this->assertSame('REGISTRATION_IDEMPOTENCY_CONFLICT', json_decode($response->getContent(), true)['code']);
        }
        $this->assertSame(1, \App\Models\RegistrationRequest::count());
    }

    public static function lockTimeouts(): array
    {
        return [[-100, 1], [500, 60]];
    }

    #[DataProvider('lockTimeouts')]
    public function test_lock_timeout_limits_and_write_connection_remain_unchanged(int $configured, int $expected): void
    {
        config(['gintly.registration.lock_timeout_seconds' => $configured]);
        DB::shouldReceive('select')->once()->with('SELECT GET_LOCK(?, ?) AS locked',
            ['gintly_reg_'.self::KEY, $expected], false)->andReturn([(object) ['locked' => 0]]);
        $error = $this->failedRegistration();
        $this->assertSame($expected, $error->waitSeconds);
        $this->assertSame('timeout', $error->reason);
    }

    public function test_real_handler_uses_fixed_stderr_json_and_error_level_without_taps_or_processors(): void
    {
        config(['logging.channels.registration.processors' => ['missing-sensitive-processor'],
            'logging.channels.registration.tap' => ['missing-sensitive-tap']]);
        $reporter = new RegistrationFailureReporter();
        $handler = (new \ReflectionMethod($reporter, 'createHandler'))->invoke($reporter);
        $this->assertInstanceOf(StreamHandler::class, $handler);
        $this->assertSame('php://stderr', $handler->getUrl());
        $this->assertSame(Level::Error, $handler->getLevel());
        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
        $handler->close();
    }

    public function test_untrusted_diagnostic_id_stage_and_reason_are_not_logged(): void
    {
        app(RegistrationFailureReporter::class)->record(new RuntimeException('raw-cause-hidden'),
            'stage-person-hidden', 'id-owner-hidden@example.test', 'reason-password-hidden');
        $context = $this->logs->getRecords()[0]->context;
        $this->assertTrue(\Illuminate\Support\Str::isUuid($context['diagnostic_id']));
        $this->assertSame('acquire_lock', $context['stage']);
        $this->assertSame('unspecified', $context['reason']);
        foreach (['stage-person-hidden', 'id-owner-hidden', 'reason-password-hidden', 'raw-cause-hidden'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($context));
        }
    }

    private function seedReplay(RegistrationService $service): void
    {
        Schema::create('registration_requests', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('fingerprint');
            $table->unsignedBigInteger('business_id');
            $table->string('business_slug');
            $table->string('owner_email');
            $table->timestamps();
        });
        \App\Models\RegistrationRequest::query()->create(['uuid' => self::KEY, 'business_id' => 1,
            'fingerprint' => (new \ReflectionMethod($service, 'fingerprint'))->invoke($service, $this->payload()),
            'business_slug' => 'qa-readonly', 'owner_email' => 'owner@example.test']);
    }

    private function failingReporter(bool $fallbackFails = false): RegistrationFailureReporter
    {
        $reporter = new class($fallbackFails) extends RegistrationFailureReporter {
            public array $fallbackRecords = [];

            public function __construct(private readonly bool $fallbackFails) {}

            protected function createHandler(): HandlerInterface
            {
                return new class extends TestHandler {
                    public function handle(LogRecord $record): bool
                    {
                        throw new RuntimeException('logger-secret-hidden');
                    }
                };
            }

            protected function writeFallback(array $context): void
            {
                $this->fallbackRecords[] = $context;
                if ($this->fallbackFails) {
                    throw new RuntimeException('fallback-secret-hidden');
                }
            }
        };
        $this->app->instance(RegistrationFailureReporter::class, $reporter);

        return $reporter;
    }

    private function failedRegistration(): RegistrationLockUnavailableException
    {
        try {
            app(RegistrationService::class)->register($this->payload(), self::KEY);
        } catch (RegistrationLockUnavailableException $error) {
            return $error;
        }
        $this->fail('Registration must not continue or write when the lock is unavailable.');
    }

    private function payload(): array
    {
        return ['business' => ['name' => 'QA Diagnostic', 'timezone' => 'America/Managua'],
            'owner' => ['first_name' => 'QA', 'last_name' => 'Diagnostic', 'email' => 'owner@example.test',
                'password' => 'Sensitive-password-not-for-logs']];
    }
}
