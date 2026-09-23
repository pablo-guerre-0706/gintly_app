<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * Caso base para pruebas HTTP contra la base MySQL REAL de esta copia
 * (gintly_backend_claude), no SQLite.
 *
 * SEGURIDAD (blindaje contra ejecución accidental sobre una base operativa):
 *   1) Exige APP_ENV=testing. En cualquier otro entorno se OMITE.
 *   2) Rechaza tajantemente APP_ENV=production (falla, no omite).
 *   3) Exige activación EXPLÍCITA: la variable de entorno GINTLY_MYSQL_TESTS debe
 *      ser verdadera. Por defecto (sin ella) estas pruebas se OMITEN, de modo que
 *      `php artisan test` normal jamás toca MySQL.
 *   4) Exige que la base configurada esté en una allowlist (solo la base de esta
 *      copia). Cualquier otra base hace fallar la prueba.
 *
 * AISLAMIENTO Y LIMPIEZA: cada prueba corre dentro de UNA transacción que se
 * revierte en tearDown. NO ejecuta DDL (create/alter/drop), NO ejecuta TRUNCATE ni
 * migraciones (no usa RefreshDatabase/migrate:fresh/refresh): solo DML que queda
 * deshecho al terminar. Por diseño no altera registros existentes ni deja rastro.
 */
abstract class MysqlTestCase extends TestCase
{
    /** Únicas bases autorizadas para estas pruebas. */
    private const AUTHORIZED_DATABASES = ['gintly_backend_claude'];

    /**
     * Crea la app forzando la conexión por defecto a la base MySQL autorizada
     * (phpunit.xml deja DB_DATABASE=:memory: y DB_URL="" para el default sqlite,
     * que contaminan también la conexión mysql). Host/usuario/contraseña siguen
     * viniendo de .env (no se exponen). El esquema ya está migrado; aquí no se toca.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', self::AUTHORIZED_DATABASES[0]);
        $app['config']->set('database.connections.mysql.url', null);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // (1) PRIMERO: rechazo tajante de production, ANTES de cualquier skip por
        //     entorno. En production se FALLA (no se omite): jamás debe pasar
        //     inadvertido que estas pruebas apuntaron a una base productiva.
        if ($this->app->environment('production')) {
            $this->fail('PROHIBIDO ejecutar pruebas MySQL con APP_ENV=production.');
        }

        // (2) Solo en entorno de pruebas (cualquier otro no-production se omite).
        $env = (string) $this->app->environment();
        if ($env !== 'testing') {
            $this->markTestSkipped("Requiere APP_ENV=testing (actual: '{$env}').");
        }

        // (3) Activación explícita.
        if (! $this->mysqlTestsEnabled()) {
            $this->markTestSkipped(
                'Pruebas MySQL desactivadas. Active GINTLY_MYSQL_TESTS=1 para ejecutarlas.'
            );
        }

        // (4) Base autorizada (allowlist).
        $database = (string) config('database.connections.mysql.database');
        if (! in_array($database, self::AUTHORIZED_DATABASES, true)) {
            $this->fail(
                "Base MySQL no autorizada para pruebas: '{$database}'. Permitidas: "
                .implode(', ', self::AUTHORIZED_DATABASES).'.'
            );
        }

        // Conexión disponible (si no, se omite; no es un falso positivo).
        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Conexión MySQL no disponible: '.$e->getMessage());
        }

        // Aislamiento: transacción envolvente. Sin DDL ni truncate.
        DB::connection('mysql')->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Revierte TODO lo creado por la prueba (limpieza garantizada).
        if ($this->app !== null) {
            $connection = DB::connection('mysql');
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }

        parent::tearDown();
    }

    private function mysqlTestsEnabled(): bool
    {
        return filter_var(env('GINTLY_MYSQL_TESTS', false), FILTER_VALIDATE_BOOL);
    }
}
