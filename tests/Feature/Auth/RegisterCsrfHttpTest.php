<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aceptación REAL de stateful + CSRF del registro público (POST /api/v1/auth/register).
 *
 * No desactiva middleware ni aprovecha la exclusión de CSRF del entorno de pruebas: levanta un SERVIDOR HTTP
 * real con `php artisan serve` (SAPI cli-server ⇒ runningInConsole()=false ⇒ ValidateCsrfToken SÍ se aplica) y
 * ejecuta el handshake SPA real con un cliente con cookies (GET /sanctum/csrf-cookie → POST con X-XSRF-TOKEN).
 * Prueba: SPA válida aceptada (201) y solicitud sin token / con token inválido rechazada (419).
 *
 * Guardas: APP_ENV=testing (del proceso de prueba), opt-in GINTLY_MYSQL_TESTS, allowlist de base, proc_open y
 * Guzzle disponibles. Fixtures confirmados por el servidor; limpieza acotada por token en orden FK-seguro.
 */
final class RegisterCsrfHttpTest extends TestCase
{
    private const DB_NAME = 'gintly_backend_claude';

    private const PASSWORD = 'Str0ng-P@ssw0rd-9xQ';

    private string $token = '';

    private string $logDir = '';

    /** @var resource|null */
    private $serverProc = null;

    private ?int $serverPid = null;

    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', self::DB_NAME);
        $app['config']->set('database.connections.mysql.url', null);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->environment('production')) {
            $this->fail('PROHIBIDO en producción.');
        }
        if ((string) $this->app->environment() !== 'testing') {
            $this->markTestSkipped('Requiere APP_ENV=testing.');
        }
        if (! filter_var(env('GINTLY_MYSQL_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Pruebas MySQL desactivadas. Active GINTLY_MYSQL_TESTS=1.');
        }
        if ((string) config('database.connections.mysql.database') !== self::DB_NAME) {
            $this->fail('Base MySQL no autorizada.');
        }
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open no disponible.');
        }
        if (! class_exists(CookieJar::class)) {
            $this->markTestSkipped('Guzzle (cookies) no disponible.');
        }
        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Conexión MySQL no disponible: '.$e->getMessage());
        }

        $this->token = 'QA-CSRF-'.Str::random(12);
        $this->logDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gintly_csrf_'.Str::random(10);
        @mkdir($this->logDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->stopServer();
        $this->cleanupToken();
        $this->rrmdir($this->logDir);
        parent::tearDown();
    }

    public function test_csrf_spa_valida_aceptada_y_sin_token_o_invalido_rechazado_419(): void
    {
        $port = $this->freePort();
        $base = 'http://127.0.0.1:'.$port;
        $this->startServer($port);
        $this->waitUntilUp($port);

        $jar = new CookieJar();
        $spa = ['Referer' => $base, 'Origin' => $base, 'Accept' => 'application/json'];

        // 1) Handshake SPA: obtiene la cookie XSRF-TOKEN + sesión.
        $csrf = Http::withOptions(['cookies' => $jar, 'http_errors' => false])
            ->withHeaders($spa)
            ->get($base.'/sanctum/csrf-cookie');
        $this->assertContainsStatus([200, 204], $csrf->status(), 'csrf-cookie');

        $xsrf = $this->xsrfFromJar($jar);
        $this->assertNotSame('', $xsrf, 'No se recibió la cookie XSRF-TOKEN.');

        // 2) SPA VÁLIDA: con X-XSRF-TOKEN → CSRF pasa → 201.
        $ok = Http::withOptions(['cookies' => $jar, 'http_errors' => false])
            ->withHeaders($spa + ['X-XSRF-TOKEN' => $xsrf, 'Idempotency-Key' => (string) Str::uuid()])
            ->post($base.'/api/v1/auth/register', $this->payload('Ok', 'ok@example.com'));
        $this->assertSame(201, $ok->status(), 'SPA válida debía ser 201. Cuerpo: '.$ok->body());

        // 3) SIN token CSRF → 419.
        $missing = Http::withOptions(['cookies' => $jar, 'http_errors' => false])
            ->withHeaders($spa + ['Idempotency-Key' => (string) Str::uuid()])
            ->post($base.'/api/v1/auth/register', $this->payload('NoTok', 'notok@example.com'));
        $this->assertSame(419, $missing->status(), 'Sin token debía ser 419. Cuerpo: '.$missing->body());

        // 4) Token CSRF inválido → 419.
        $bad = Http::withOptions(['cookies' => $jar, 'http_errors' => false])
            ->withHeaders($spa + ['X-XSRF-TOKEN' => 'token-invalido', 'Idempotency-Key' => (string) Str::uuid()])
            ->post($base.'/api/v1/auth/register', $this->payload('BadTok', 'badtok@example.com'));
        $this->assertSame(419, $bad->status(), 'Token inválido debía ser 419. Cuerpo: '.$bad->body());

        // Solo el intento válido creó negocio (los 419 no escriben nada).
        $this->assertSame(1, DB::table('businesses')->where('name', 'like', '%'.$this->token.'%')->count());
    }

    // ============================= Infraestructura =============================

    /** @return array<string, mixed> */
    private function payload(string $suffix, string $email): array
    {
        return [
            'business' => ['name' => $this->token.' '.$suffix, 'timezone' => 'America/Managua'],
            'owner'    => [
                'first_name' => 'Pablo', 'last_name' => 'Guerrero', 'email' => $email,
                'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            ],
        ];
    }

    private function freePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            $this->markTestSkipped('No se pudo reservar un puerto libre: '.$errstr);
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);

        return (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
    }

    private function startServer(int $port): void
    {
        // SANCTUM_STATEFUL_DOMAINS incluye nuestro origen; APP_ENV no-testing (serve bajo cli-server ⇒ CSRF
        // real); SESSION_DRIVER=file para que la sesión persista entre el GET csrf-cookie y el POST.
        $overrides = [
            'APP_ENV'                  => 'local',  // NO testing ⇒ (con cli-server) CSRF real
            'APP_DEBUG'                => 'false',
            'APP_URL'                  => 'http://127.0.0.1:'.$port,
            'DB_CONNECTION'            => 'mysql',   // el proceso de prueba inyecta sqlite; forzamos mysql
            'DB_DATABASE'              => self::DB_NAME,
            'DB_URL'                   => '',        // phpunit.xml lo deja vacío; evita que contamine mysql
            'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:'.$port.',127.0.0.1,localhost',
            'SESSION_DRIVER'           => 'file',    // la sesión debe persistir entre csrf-cookie y el POST
            'CACHE_STORE'              => 'file',
        ];
        $env = array_merge($this->currentEnv(), $overrides);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $this->logDir.DIRECTORY_SEPARATOR.'server.out', 'a'],
            2 => ['file', $this->logDir.DIRECTORY_SEPARATOR.'server.err', 'a'],
        ];

        $cmd = [PHP_BINARY, base_path('artisan'), 'serve', '--host=127.0.0.1', '--port='.$port];

        $proc = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        if (! is_resource($proc)) {
            $this->markTestSkipped('No se pudo lanzar el servidor de pruebas.');
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $this->serverProc = $proc;
        $status = proc_get_status($proc);
        $this->serverPid = (int) ($status['pid'] ?? 0);
    }

    private function waitUntilUp(int $port): void
    {
        $deadline = microtime(true) + 20.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
            if (is_resource($conn)) {
                fclose($conn);

                return;
            }
            usleep(50_000); // 50 ms de reintento de readiness (no es sincronización de concurrencia).
        }

        $this->markTestSkipped('El servidor de pruebas no respondió a tiempo. err='.$this->readLog('server.err'));
    }

    private function xsrfFromJar(CookieJar $jar): string
    {
        foreach ($jar->toArray() as $cookie) {
            if (($cookie['Name'] ?? null) === 'XSRF-TOKEN') {
                return urldecode((string) ($cookie['Value'] ?? ''));
            }
        }

        return '';
    }

    /** @param array<int,int> $allowed */
    private function assertContainsStatus(array $allowed, int $status, string $ctx): void
    {
        $this->assertTrue(in_array($status, $allowed, true), "Estado inesperado en {$ctx}: {$status}");
    }

    /** @return array<string,string> */
    private function currentEnv(): array
    {
        $env = [];
        foreach ((array) getenv() as $k => $v) {
            $env[(string) $k] = (string) $v;
        }

        // El proceso de PHPUnit inyecta variables de prueba (phpunit.xml) que NO deben llegar al servidor
        // hijo: lo pondrían en sqlite :memory: / entorno testing. Se retiran para que el hijo lea .env y los
        // overrides explícitos (DB_HOST/USUARIO/CLAVE reales provienen de .env, no de aquí).
        foreach ([
            'GINTLY_MYSQL_TESTS', 'APP_ENV', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION',
            'CACHE_STORE', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'MAIL_MAILER', 'QUEUE_CONNECTION',
            'SESSION_DRIVER', 'PULSE_ENABLED', 'TELESCOPE_ENABLED', 'NIGHTWATCH_ENABLED',
        ] as $k) {
            unset($env[$k]);
        }

        return $env;
    }

    private function stopServer(): void
    {
        if ($this->serverPid !== null && $this->serverPid > 0) {
            // taskkill /T derriba también el proceso hijo `php -S` que crea artisan serve en Windows.
            @exec('taskkill /F /T /PID '.$this->serverPid.' 2>&1');
        }
        if (is_resource($this->serverProc)) {
            @proc_terminate($this->serverProc);
            @proc_close($this->serverProc);
        }
        $this->serverProc = null;
        $this->serverPid = null;
    }

    private function readLog(string $file): string
    {
        $path = $this->logDir.DIRECTORY_SEPARATOR.$file;

        return is_file($path) ? (string) @file_get_contents($path) : '';
    }

    private function cleanupToken(): void
    {
        // Prueba omitida: token nunca fijado → nada que limpiar (evita un LIKE '%%' que casaría con todo).
        if ($this->token === '') {
            return;
        }

        try {
            $ids = DB::table('businesses')->where('name', 'like', '%'.$this->token.'%')->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all();
            if ($ids === []) {
                return;
            }
            $roleIds = DB::table('roles')->whereIn('business_id', $ids)->pluck('id')->all();
            if ($roleIds !== []) {
                DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
            }
            DB::table('model_has_roles')->whereIn('business_id', $ids)->delete();
            DB::table('roles')->whereIn('business_id', $ids)->delete();
            DB::table('registration_requests')->whereIn('business_id', $ids)->delete();
            foreach (['tax_rules', 'anomaly_rules', 'document_sequences', 'customers'] as $table) {
                DB::table($table)->whereIn('business_id', $ids)->delete();
            }
            DB::table('users')->whereIn('business_id', $ids)->delete();
            DB::table('businesses')->whereIn('id', $ids)->delete();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Cleanup CSRF test: '.$e->getMessage().PHP_EOL);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
