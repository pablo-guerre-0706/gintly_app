<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aceptación de CONCURRENCIA REAL del registro público (POST /api/v1/auth/register vía RegistrationService).
 *
 * A diferencia de MysqlTestCase, esta prueba NO envuelve el test en una transacción revertida: lanza DOS
 * procesos PHP reales (proc_open) con conexiones MySQL independientes que CONFIRMAN de verdad, sincronizados
 * por una barrera de archivos con timeout finito. Ejecuta el RegistrationService REAL (no simula el índice
 * UNIQUE). Fixtures mínimos; limpieza acotada a las filas marcadas con un token exclusivo de esta prueba.
 *
 * Guardas: solo APP_ENV=testing, base en la allowlist, opt-in GINTLY_MYSQL_TESTS y proc_open disponible.
 */
final class RegisterConcurrencyTest extends TestCase
{
    private const DB_NAME = 'gintly_backend_claude';

    private const PASSWORD = 'Str0ng-P@ssw0rd-9xQ';

    private string $token = '';

    private string $barrierDir = '';

    /**
     * App apuntando a la base MySQL autorizada, SIN transacción envolvente (los procesos hijos deben ver
     * fixtures confirmados y esta prueba debe leer y limpiar lo que confirmaron).
     */
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
            $this->fail('PROHIBIDO ejecutar pruebas MySQL con APP_ENV=production.');
        }
        if ((string) $this->app->environment() !== 'testing') {
            $this->markTestSkipped('Requiere APP_ENV=testing.');
        }
        if (! filter_var(env('GINTLY_MYSQL_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Pruebas MySQL desactivadas. Active GINTLY_MYSQL_TESTS=1.');
        }
        if ((string) config('database.connections.mysql.database') !== self::DB_NAME) {
            $this->fail('Base MySQL no autorizada para pruebas.');
        }
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open no disponible: no se puede probar concurrencia multiproceso.');
        }
        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Conexión MySQL no disponible: '.$e->getMessage());
        }

        $this->token = 'QA-CONC-'.Str::random(12);
        $this->barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gintly_conc_'.Str::random(10);
        @mkdir($this->barrierDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupToken();
        $this->rrmdir($this->barrierDir);

        parent::tearDown();
    }

    // ============================= Escenarios =============================

    public function test_misma_clave_mismo_payload_simultaneos_un_solo_negocio(): void
    {
        $key = (string) Str::uuid();
        $payload = $this->payload('Cafe Uno', 'uno@example.com');

        [$a, $b] = $this->runPair($payload, $key, $payload, $key);

        // Ambas solicitudes tienen éxito y DEVUELVEN EL MISMO resultado público.
        $this->assertTrue($a['ok'] ?? false, 'A falló: '.json_encode($a));
        $this->assertTrue($b['ok'] ?? false, 'B falló: '.json_encode($b));
        $this->assertSame($a['result'], $b['result']);

        // Un solo negocio, un propietario, un registro de idempotencia.
        $ids = $this->businessIds();
        $this->assertCount(1, $ids);
        $this->assertSame(1, DB::table('users')->whereIn('business_id', $ids)->count());
        $this->assertSame(1, DB::table('registration_requests')->where('uuid', $key)->count());

        // Aprovisionamiento ejecutado EXACTAMENTE una vez (no duplicado por el perdedor).
        $this->assertSame(6, DB::table('anomaly_rules')->whereIn('business_id', $ids)->count());
        $this->assertSame(1, DB::table('customers')->whereIn('business_id', $ids)->where('is_generic', 1)->count());

        // Teams restaurados en ambos procesos.
        $this->assertNull($a['team_after']);
        $this->assertNull($b['team_after']);
    }

    public function test_misma_clave_payload_distinto_simultaneos_un_ganador_un_conflicto(): void
    {
        $key = (string) Str::uuid();
        $payloadA = $this->payload('Negocio A', 'a@example.com');
        $payloadB = $this->payload('Negocio B', 'b@example.com');

        [$a, $b] = $this->runPair($payloadA, $key, $payloadB, $key);

        $oks = array_filter([$a, $b], static fn (array $r): bool => ($r['ok'] ?? false) === true);
        $conflicts = array_filter([$a, $b], static fn (array $r): bool => ($r['ok'] ?? null) === false);

        // Exactamente un ganador (201) y un conflicto estable de idempotencia.
        $this->assertCount(1, $oks, 'Debe haber exactamente un ganador: '.json_encode([$a, $b]));
        $this->assertCount(1, $conflicts, 'Debe haber exactamente un conflicto: '.json_encode([$a, $b]));

        $loser = array_values($conflicts)[0];
        $this->assertSame(\App\Exceptions\RegistrationIdempotencyConflictException::class, $loser['exception'] ?? null);

        // Sin filas parciales del perdedor: un solo negocio, un propietario, un registro de idempotencia.
        $ids = $this->businessIds();
        $this->assertCount(1, $ids);
        $this->assertSame(1, DB::table('users')->whereIn('business_id', $ids)->count());
        $this->assertSame(1, DB::table('registration_requests')->where('uuid', $key)->count());

        // El negocio superviviente es el del GANADOR.
        $winner = array_values($oks)[0];
        $this->assertSame(
            $winner['result']['business_slug'],
            (string) DB::table('businesses')->whereIn('id', $ids)->value('slug')
        );

        $this->assertNull($a['team_after']);
        $this->assertNull($b['team_after']);
    }

    public function test_claves_distintas_mismo_nombre_simultaneos_dos_negocios_slugs_distintos(): void
    {
        $keyA = (string) Str::uuid();
        $keyB = (string) Str::uuid();
        $payloadA = $this->payload('Mismo Nombre', 'ca@example.com');
        $payloadB = $this->payload('Mismo Nombre', 'cb@example.com');

        [$a, $b] = $this->runPair($payloadA, $keyA, $payloadB, $keyB);

        $this->assertTrue($a['ok'] ?? false, 'A falló: '.json_encode($a));
        $this->assertTrue($b['ok'] ?? false, 'B falló: '.json_encode($b));

        // Dos negocios válidos, dos propietarios, dos registros de idempotencia.
        $ids = $this->businessIds();
        $this->assertCount(2, $ids);
        $this->assertSame(2, DB::table('users')->whereIn('business_id', $ids)->count());
        $this->assertSame(2, DB::table('registration_requests')->whereIn('uuid', [$keyA, $keyB])->count());

        // Slugs DIFERENTES (colisión real resuelta con sufijo).
        $this->assertNotSame($a['result']['business_slug'], $b['result']['business_slug']);
        $slugs = DB::table('businesses')->whereIn('id', $ids)->pluck('slug')->all();
        $this->assertCount(2, array_unique($slugs));

        $this->assertNull($a['team_after']);
        $this->assertNull($b['team_after']);
    }

    // ============================= Infraestructura de la prueba =============================

    /** @return array<string, mixed> */
    private function payload(string $nameSuffix, string $email): array
    {
        return [
            'business' => [
                'name'     => $this->token.' '.$nameSuffix,
                'timezone' => 'America/Managua',
            ],
            'owner' => [
                'first_name' => 'Pablo',
                'last_name'  => 'Guerrero',
                'email'      => $email,
                'password'   => self::PASSWORD,
            ],
        ];
    }

    /**
     * Lanza dos procesos worker con la misma barrera y devuelve sus salidas JSON decodificadas.
     *
     * @param  array<string, mixed>  $payloadA
     * @param  array<string, mixed>  $payloadB
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function runPair(array $payloadA, string $keyA, array $payloadB, string $keyB): array
    {
        $cfgA = $this->writeConfig(0, $payloadA, $keyA);
        $cfgB = $this->writeConfig(1, $payloadB, $keyB);

        // Ambos procesos se lanzan ANTES de leer: así los dos arrancan, alcanzan la barrera y corren a la vez.
        [$procA, $pipesA] = $this->spawn($cfgA);
        [$procB, $pipesB] = $this->spawn($cfgB);

        $outA = stream_get_contents($pipesA[1]);
        $errA = stream_get_contents($pipesA[2]);
        $outB = stream_get_contents($pipesB[1]);
        $errB = stream_get_contents($pipesB[2]);

        foreach ([$pipesA, $pipesB] as $pipes) {
            fclose($pipes[1]);
            fclose($pipes[2]);
        }
        proc_close($procA);
        proc_close($procB);

        $a = json_decode(trim((string) $outA), true);
        $b = json_decode(trim((string) $outB), true);

        $this->assertIsArray($a, "Salida inválida del worker A. stdout=[{$outA}] stderr=[{$errA}]");
        $this->assertIsArray($b, "Salida inválida del worker B. stdout=[{$outB}] stderr=[{$errB}]");
        $this->assertArrayNotHasKey('fatal', $a, 'Worker A fatal: '.json_encode($a).' stderr=['.$errA.']');
        $this->assertArrayNotHasKey('fatal', $b, 'Worker B fatal: '.json_encode($b).' stderr=['.$errB.']');

        return [$a, $b];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeConfig(int $slot, array $payload, string $key): string
    {
        $cfg = [
            'base'    => base_path(),
            'db'      => self::DB_NAME,
            'slot'    => $slot,
            'peers'   => 2,
            'barrier' => $this->barrierDir,
            'timeout' => 20.0,
            'payload' => $payload,
            'key'     => $key,
        ];

        $path = $this->barrierDir.DIRECTORY_SEPARATOR.'config.'.$slot.'.json';
        file_put_contents($path, json_encode($cfg));

        return $path;
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function spawn(string $configPath): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cmd = [PHP_BINARY, __DIR__.DIRECTORY_SEPARATOR.'registration_concurrency_worker.php', $configPath];

        $proc = proc_open($cmd, $descriptors, $pipes, base_path(), null);

        if (! is_resource($proc)) {
            $this->fail('No se pudo lanzar el proceso worker.');
        }

        fclose($pipes[0]); // sin stdin

        return [$proc, $pipes];
    }

    /** @return array<int, int> IDs de los negocios creados por ESTA prueba (por token). */
    private function businessIds(): array
    {
        return DB::table('businesses')
            ->where('name', 'like', '%'.$this->token.'%')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** Limpieza acotada EXCLUSIVAMENTE a las filas marcadas con el token de esta prueba (orden FK-seguro). */
    private function cleanupToken(): void
    {
        // Prueba omitida (p. ej. sin opt-in): el token nunca se fijó → no hay nada que limpiar. NUNCA
        // consultar con token vacío (un LIKE '%%' casaría con TODOS los negocios).
        if ($this->token === '') {
            return;
        }

        try {
            $ids = $this->businessIds();
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
            DB::table('businesses')->whereIn('id', $ids)->delete(); // hard delete (query builder, no soft-delete).
        } catch (\Throwable $e) {
            // La limpieza nunca debe enmascarar el resultado de la prueba; se reporta y se continúa.
            fwrite(STDERR, 'Cleanup concurrency test: '.$e->getMessage().PHP_EOL);
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
