<?php

declare(strict_types=1);

/**
 * Worker de CONCURRENCIA REAL del registro público (lo lanza RegisterConcurrencyTest con proc_open, un proceso
 * por solicitud, cada uno con su propia conexión MySQL). NO es una prueba PHPUnit (no termina en Test.php, por
 * lo que el runner no lo recoge) ni un sustituto del índice UNIQUE: ejecuta el RegistrationService REAL y
 * confirma de verdad. La sincronización es una BARRERA de archivos con timeout finito: ambos procesos se
 * declaran listos y esperan (spin, con un micro-yield de CPU, no un sleep de temporización) a que todos los
 * pares estén listos, de modo que invoquen el servicio casi en el mismo instante.
 *
 * Entrada: argv[1] = ruta a un JSON { base, db, slot, peers, barrier, timeout, payload, key }.
 * Salida (stdout, JSON): { slot, ok, result|exception|message, team_after } o { ok:false, fatal }.
 */

// Entorno de pruebas ANTES del bootstrap (Dotenv inmutable no sobrescribe variables ya presentes).
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

$configPath = $argv[1] ?? null;
if ($configPath === null || ! is_file($configPath)) {
    fwrite(STDOUT, json_encode(['ok' => false, 'fatal' => 'no_config']));
    exit(0);
}

/** @var array<string, mixed> $cfg */
$cfg  = json_decode((string) file_get_contents($configPath), true) ?: [];
$slot = (int) ($cfg['slot'] ?? -1);

$fail = static function (string $reason) use ($slot): never {
    fwrite(STDOUT, json_encode(['slot' => $slot, 'ok' => false, 'fatal' => $reason]));
    exit(0);
};

$base = (string) ($cfg['base'] ?? '');
if ($base === '' || ! is_file($base.'/vendor/autoload.php')) {
    $fail('bad_base');
}

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Fuerza la conexión MySQL autorizada (defensa; evita cualquier contaminación de conexión por defecto).
$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql.database', (string) $cfg['db']);
$app['config']->set('database.connections.mysql.url', null);

// Guardas de seguridad: EXCLUSIVAMENTE entorno testing y base en la allowlist.
if (! $app->environment('testing')) {
    $fail('env_not_testing');
}
if ((string) $cfg['db'] !== 'gintly_backend_claude') {
    $fail('db_not_authorized');
}

$barrier = (string) $cfg['barrier'];
$peers   = (int) $cfg['peers'];
$timeout = (float) $cfg['timeout'];

// Barrera explícita: declara "listo" y espera a que TODOS los pares lo estén (timeout finito).
@file_put_contents($barrier.'/ready.'.$slot, '1');

$deadline = microtime(true) + $timeout;
$allReady = static function () use ($barrier, $peers): bool {
    for ($i = 0; $i < $peers; $i++) {
        if (! is_file($barrier.'/ready.'.$i)) {
            return false;
        }
    }

    return true;
};

while (! $allReady()) {
    if (microtime(true) > $deadline) {
        $fail('barrier_timeout');
    }
    // Micro-yield de CPU para permitir el avance REAL del otro proceso (no es un sleep de temporización;
    // la corrección proviene de la barrera y del índice UNIQUE, no de esta duración).
    usleep(100);
}

$registrar = $app->make(Spatie\Permission\PermissionRegistrar::class);

try {
    $result = $app->make(App\Services\Auth\RegistrationService::class)
        ->register((array) $cfg['payload'], (string) $cfg['key']);

    $out = ['slot' => $slot, 'ok' => true, 'result' => $result];
} catch (\Throwable $e) {
    $out = ['slot' => $slot, 'ok' => false, 'exception' => get_class($e), 'message' => $e->getMessage()];
}

// Verificación de restauración de team: tras register, el PermissionRegistrar debe volver a su valor previo
// (null en un proceso recién arrancado).
$out['team_after'] = $registrar->getPermissionsTeamId();

fwrite(STDOUT, json_encode($out));
exit(0);
