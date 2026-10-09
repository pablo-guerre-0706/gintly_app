<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PDOException;
use RuntimeException;
use Throwable;

/** Diagnostics only: no requests, SQL/bindings, exception messages, traces, DSNs or tenant data. */
class RegistrationFailureReporter
{
    public function record(
        Throwable $error,
        string $stage,
        ?string $diagnosticId = null,
        string $reason = 'exception',
        ?int $waitSeconds = null,
    ): void {
        // Keep correlation available even if building metadata or either logging sink fails.
        $context = [
            'diagnostic_id' => preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/Di', $diagnosticId ?? '')
                ? $diagnosticId : null,
            'endpoint' => '/api/v1/auth/register',
            'stage' => $stage === 'release_lock' ? 'release_lock' : 'acquire_lock',
        ];

        try {
            $context = $this->context($error, $context, $reason, $waitSeconds);
            // Laravel channels inherit shared/request Context and dispatch logging listeners.
            // Use an isolated Monolog instance: no processors, listeners or emergency raw reports.
            (new Logger('registration', [$this->createHandler()]))
                ->error('registration.infrastructure_failure', $context);
        } catch (Throwable) {
            try {
                $this->writeFallback($context);
            } catch (Throwable) {
                // Logging is best-effort. Neither sink may replace the original HTTP/result.
            }
        }
    }

    private function context(Throwable $error, array $context, string $reason, ?int $waitSeconds): array
    {
        $cause = $error;
        // Bound traversal defensively; no raw exception is given to a logger.
        for ($depth = 0; $depth < 8 && $cause->getPrevious() !== null; $depth++) {
            $cause = $cause->getPrevious();
        }

        $info = $cause instanceof PDOException ? ($cause->errorInfo ?? []) : [];
        $code = $cause->getCode();
        $stateCandidate = $info[0] ?? $code;
        $driverCandidate = $info[1] ?? ($cause instanceof PDOException && is_int($code) && $code !== 0 ? $code : null);
        $sqlstate = preg_match('/^[A-Z0-9]{5}$/D', (string) $stateCandidate) ? (string) $stateCandidate : null;
        $driverCode = $driverCandidate !== null && preg_match('/^\d{1,8}$/D', (string) $driverCandidate)
            ? (int) $driverCandidate : null;
        $connection = (string) config('database.default');
        $connectionConfig = (array) config('database.connections.'.$connection, []);
        try {
            // DB_URL can override the driver/host/database. Resolve it without connecting or logging it.
            $effectiveConfig = (new ConfigurationUrlParser())->parseConfiguration($connectionConfig);
            $driver = $effectiveConfig['driver'] ?? null;
        } catch (Throwable) {
            $driver = null;
        }
        $source = str_replace('\\', '/', $cause->getFile());
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        $context['diagnostic_id'] ??= (string) Str::uuid();

        return $context + [
            'reason' => in_array($reason, ['exception', 'timeout', 'null_result', 'unexpected_result'], true)
                ? $reason : 'unspecified',
            'exception_class' => $this->className($error),
            'cause_class' => $this->className($cause),
            'source_file' => str_starts_with($source, $base) ? substr($source, strlen($base)) : '[external]',
            'source_line' => $cause->getLine(),
            'exception_code' => preg_match('/^[A-Z0-9]{1,8}$/D', (string) $code) ? (string) $code : null,
            'sqlstate' => $sqlstate,
            'driver_code' => $driverCode,
            'category' => $this->category($driverCode),
            'effective_driver' => in_array($driver, ['mysql', 'mariadb', 'sqlite', 'pgsql', 'sqlsrv'], true) ? $driver : 'other',
            'db_url_present' => (bool) config('database.connections.'.$connection.'.url'),
            'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
            'configuration_cached' => app()->configurationIsCached(),
            'app_key_present' => (bool) config('app.key'),
            'wait_seconds' => $waitSeconds === null ? null : min(60, max(1, $waitSeconds)),
        ];
    }

    /** Fixed, locally configured sink; never import Laravel Context, taps or processors. */
    protected function createHandler(): HandlerInterface
    {
        $config = (array) config('logging.channels.registration', []);
        if (($config['driver'] ?? null) !== 'monolog' || ($config['name'] ?? null) !== 'registration'
            || ($config['handler'] ?? null) !== StreamHandler::class
            || ($config['handler_with']['stream'] ?? null) !== 'php://stderr'
            || ($config['formatter'] ?? null) !== JsonFormatter::class || ($config['level'] ?? null) !== 'error') {
            throw new RuntimeException('Registration diagnostic sink unavailable.');
        }

        $handler = new StreamHandler($config['handler_with']['stream'], $config['level']);
        $handler->setFormatter(new JsonFormatter());

        return $handler;
    }

    protected function writeFallback(array $context): void
    {
        // Suppress sink warnings too; a PHP error handler may otherwise turn them into an exception.
        @error_log((string) json_encode([
            'message' => 'registration.infrastructure_failure', 'context' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function className(Throwable $error): string
    {
        $class = $error::class;

        return preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/D', $class) ? $class : 'Throwable';
    }

    private function category(?int $code): string
    {
        return match ($code) {
            1044 => 'database_permission_denied',
            1045 => 'database_authentication_denied',
            1049 => 'database_unknown',
            1146 => 'database_table_missing',
            1205 => 'database_lock_wait_timeout',
            1213 => 'database_deadlock',
            1305 => 'database_function_unavailable',
            2002, 2003 => 'database_connection_failed',
            2006, 2013 => 'database_connection_lost',
            2026 => 'database_tls_failed',
            default => 'unclassified',
        };
    }
}
