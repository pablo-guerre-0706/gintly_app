<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\BusinessStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessSlugConflictException;
use App\Exceptions\RegistrationIdempotencyConflictException;
use App\Exceptions\RegistrationLockUnavailableException;
use App\Models\Business;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Orquestación transaccional del alta pública canónica. TODO-O-NADA: Business + aprovisionamiento del
 * Observer + propietario + owner_user_id + ROL-01 (team Spatie) + fila de idempotencia ocurren dentro de
 * UNA sola transacción exterior en la misma conexión. Cualquier fallo revierte TODO; no hay commits
 * intermedios. El alta NO autentica (no hay Auth::login ni tokens).
 */
final class RegistrationService
{
    private const SLUG_MAX = 160;

    private const SLUG_SUFFIX_RESERVE = 9; // '-' + 8 chars aleatorios

    public function __construct(
        private readonly PermissionRegistrar $permissions,
    ) {
    }

    /**
     * @param  array{business: array{name: string, timezone: string}, owner: array{first_name: string, last_name: string, email: string, password: string}}  $data
     * @return array{business_slug: string, owner_email: string}
     */
    public function register(array $data, string $idempotencyKey): array
    {
        $fingerprint = $this->fingerprint($data);

        // Serializa las solicitudes con la MISMA Idempotency-Key mediante un lock con nombre de MySQL (espera
        // acotada). Dos altas idénticas simultáneas, si corrieran a la vez todo el aprovisionamiento antes de
        // chocar en el índice UNIQUE, pueden INTERBLOQUEARSE (1213) en vez de resolver limpio; el lock por clave
        // las serializa (la perdedora encuentra ya resuelto al ganador). Claves DISTINTAS usan locks distintos:
        // paralelismo pleno. El índice UNIQUE de uuid sigue siendo el árbitro definitivo (backstop del lock).
        $lockName = $this->lockName($idempotencyKey);

        // Solo se continúa si GET_LOCK CONFIRMA la adquisición (1). Un timeout (0) o un error (NULL) NO son
        // éxito: son indisponibilidad de infraestructura → 500 sanitizado (nunca un 409 de slug/idempotencia).
        if ($this->acquireLock($lockName) !== true) {
            throw new RegistrationLockUnavailableException();
        }

        try {
            // Lectura NUEVA previa: si la clave ya resolvió, reutiliza o rechaza según el fingerprint, sin
            // ejecutar de nuevo el aprovisionamiento.
            $existing = RegistrationRequest::query()->where('uuid', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->resolveExisting($existing, $fingerprint);
            }

            return $this->runResilient($data, $idempotencyKey, $fingerprint);
        } finally {
            // Liberación explícita en la MISMA conexión (éxito, replay, conflicto, excepción o agotamiento de
            // reintentos). El commit/rollback de la transacción NO libera el lock con nombre.
            $this->releaseLock($lockName);
        }
    }

    /**
     * Bucle acotado: reintenta ante colisión de slug (slug nuevo), deadlock (reintento) y colisión de uuid
     * (ganador concurrente → resolver tras rollback). Cada intento es una transacción exterior independiente.
     *
     * @param  array{business: array{name: string, timezone: string}, owner: array{first_name: string, last_name: string, email: string, password: string}}  $data
     * @return array{business_slug: string, owner_email: string}
     */
    private function runResilient(array $data, string $idempotencyKey, string $fingerprint): array
    {
        $slugRetries     = max(0, (int) config('gintly.registration.slug_retries', 5));
        $deadlockRetries = max(0, (int) config('gintly.registration.deadlock_retries', 3));

        $slugAttempt     = 0;
        $deadlockAttempt = 0;
        $uuidAttempt     = 0;

        while (true) {
            $slug = $this->buildSlug($data['business']['name'], $slugAttempt);

            try {
                return $this->persist($data, $idempotencyKey, $fingerprint, $slug);
            } catch (QueryException $e) {
                if ($this->isUuidConflict($e)) {
                    // Un intento concurrente con la MISMA clave ganó el índice entre la lectura previa y
                    // nuestra inserción. Este intento ya hizo rollback integral (excepción fuera de la tx);
                    // resolvemos con una LECTURA NUEVA, sin reutilizar ninguna fotografía transaccional.
                    $winner = RegistrationRequest::query()->where('uuid', $idempotencyKey)->first();
                    if ($winner !== null) {
                        return $this->resolveExisting($winner, $fingerprint);
                    }
                    // El ganador también se revirtió: reintento seguro, acotado.
                    if ($uuidAttempt++ >= $deadlockRetries) {
                        throw $e;
                    }
                    continue;
                }

                if ($this->isSlugConflict($e)) {
                    if ($slugAttempt++ >= $slugRetries) {
                        throw new BusinessSlugConflictException();
                    }
                    continue;
                }

                if ($this->isDeadlock($e)) {
                    if ($deadlockAttempt++ >= $deadlockRetries) {
                        throw $e;
                    }
                    continue;
                }

                // Cualquier otro error SQL NO se disfraza de colisión de slug/idempotencia.
                throw $e;
            }
        }
    }

    /**
     * Un intento = una transacción exterior. Business (defaults de backend) → Observer síncrono (misma
     * conexión) → propietario → owner_user_id → ROL-01 bajo el team del negocio → fila de idempotencia.
     *
     * @param  array{business: array{name: string, timezone: string}, owner: array{first_name: string, last_name: string, email: string, password: string}}  $data
     * @return array{business_slug: string, owner_email: string}
     */
    private function persist(array $data, string $idempotencyKey, string $fingerprint, string $slug): array
    {
        // Guarda el team vigente ANTES de tocarlo; se restaura SIEMPRE (éxito, error, reintento).
        $previousTeam = $this->permissions->getPermissionsTeamId();

        try {
            return DB::transaction(function () use ($data, $idempotencyKey, $fingerprint, $slug): array {
                // 4. Business con defaults CANÓNICOS controlados por backend; owner_user_id = null.
                $business = Business::query()->create([
                    'name'          => $data['business']['name'],
                    'slug'          => $slug,
                    'owner_user_id' => null,
                    'plan'          => 'basic',
                    'status'        => BusinessStatus::Trial->value,
                    'timezone'      => $data['business']['timezone'],
                    // tax_rate: lo fija el default del esquema (no se acepta del cliente).
                ]);

                // 5. El evento `created` dispara BusinessObserver AUTOMÁTICAMENTE (cliente genérico, secuencias,
                //    reglas de anomalía, configuración fiscal y matriz de roles del negocio), síncrono y en la
                //    misma conexión/transacción. No se invoca manualmente ni se registra dos veces.

                // 6. Propietario del negocio recién creado. business_id NO es fillable → se asigna directo.
                $owner = new User([
                    'name'      => trim($data['owner']['first_name'].' '.$data['owner']['last_name']),
                    'email'     => $data['owner']['email'],
                    'password'  => $data['owner']['password'], // el cast 'hashed' cifra UNA vez (sin doble hash).
                    'is_active' => true,
                    'branch_id' => null,
                ]);
                $owner->business_id = $business->id;
                $owner->save();

                // 7. Vínculo del propietario.
                $business->owner_user_id = $owner->id;
                $business->save();

                // 8. Team del nuevo negocio + ROL-01 exacto (guard web). El Observer ya materializó los roles
                //    del negocio; syncRoles garantiza EXACTAMENTE un rol. Se invalida la caché de permisos.
                $this->permissions->setPermissionsTeamId($business->id);
                $this->permissions->forgetCachedPermissions();
                $owner->syncRoles([RoleName::Owner->value]);

                // 9. Idempotencia persistente: uuid (UNIQUE) dentro de la MISMA transacción. Resultado público.
                RegistrationRequest::query()->create([
                    'uuid'          => $idempotencyKey,
                    'fingerprint'   => $fingerprint,
                    'business_id'   => $business->id,
                    'business_slug' => $business->slug,
                    'owner_email'   => $owner->email,
                ]);

                // 10/11. El commit lo realiza DB::transaction al retornar; el resultado alimenta el Resource.
                return [
                    'business_slug' => (string) $business->slug,
                    'owner_email'   => (string) $owner->email,
                ];
            });
        } finally {
            // Restaura el team previo y limpia la caché pase lo que pase (incluye rollback y reintentos).
            $this->permissions->setPermissionsTeamId($previousTeam);
            $this->permissions->forgetCachedPermissions();
        }
    }

    /**
     * @return array{business_slug: string, owner_email: string}
     */
    private function resolveExisting(RegistrationRequest $existing, string $fingerprint): array
    {
        // Comparación en tiempo constante: misma clave + mismo payload → mismo 201; payload distinto → 409.
        if (! hash_equals((string) $existing->fingerprint, $fingerprint)) {
            throw new RegistrationIdempotencyConflictException();
        }

        return $existing->publicResult();
    }

    // ---------------- Fingerprint ----------------

    /**
     * HMAC-SHA256 sobre una representación CANÓNICA y VERSIONADA del payload validado/normalizado. Incluye la
     * contraseña EXACTA (no se persiste). La confirmación se excluye (ya validada idéntica). El orden de claves
     * JSON no altera el resultado (claves ordenadas de forma determinista). Depende de un secreto ESTABLE.
     *
     * @param  array{business: array{name: string, timezone: string}, owner: array{first_name: string, last_name: string, email: string, password: string}}  $data
     */
    private function fingerprint(array $data): string
    {
        $canonical = [
            'v'        => (string) config('gintly.registration.fingerprint_version', 'v1'),
            'business' => [
                'name'     => $data['business']['name'],
                'timezone' => $data['business']['timezone'],
            ],
            'owner' => [
                'first_name' => $data['owner']['first_name'],
                'last_name'  => $data['owner']['last_name'],
                'email'      => $data['owner']['email'],
                'password'   => $data['owner']['password'],
            ],
        ];

        $this->ksortRecursive($canonical);

        $payload = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', (string) $payload, $this->fingerprintSecret());
    }

    private function fingerprintSecret(): string
    {
        $secret = (string) (config('gintly.registration.fingerprint_secret') ?? '');

        // Respaldo estable gestionado por backend: APP_KEY (no rota entre solicitud original y repeticiones).
        return $secret !== '' ? $secret : (string) config('app.key');
    }

    private function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
        unset($value);

        ksort($array);
    }

    // ---------------- Slug ----------------

    /**
     * Slug derivado EXCLUSIVAMENTE del nombre del negocio. Intento 0 = candidato legible; intentos siguientes
     * añaden un sufijo aleatorio fuerte. Tope físico 160 reservando espacio para el sufijo. Base segura
     * `negocio` si la normalización queda vacía.
     */
    private function buildSlug(string $businessName, int $attempt): string
    {
        $base = Str::slug($businessName);

        if ($base === '') {
            $base = 'negocio';
        }

        if ($attempt === 0) {
            return Str::limit($base, self::SLUG_MAX, '');
        }

        // Sufijo aleatorio suficientemente fuerte; se reserva espacio recortando la base.
        $base   = Str::limit($base, self::SLUG_MAX - self::SLUG_SUFFIX_RESERVE, '');
        $suffix = Str::lower(Str::random(8));

        return $base.'-'.$suffix;
    }

    // ---------------- Clasificación de errores de motor ----------------

    private function isUuidConflict(QueryException $e): bool
    {
        return $this->isDuplicate($e) && str_contains($e->getMessage(), 'uniq_registration_request_uuid');
    }

    private function isSlugConflict(QueryException $e): bool
    {
        // SOLO el índice único del slug de businesses. Otros duplicados NO son colisión de slug.
        return $this->isDuplicate($e) && str_contains($e->getMessage(), 'businesses_slug_unique');
    }

    private function isDuplicate(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    private function isDeadlock(QueryException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        // 1213 deadlock; 1205 lock wait timeout.
        return $code === 1213 || $code === 1205;
    }

    // ---------------- Lock por clave de idempotencia (serialización de duplicados) ----------------

    private function lockName(string $idempotencyKey): string
    {
        // El uuid ya viene validado/canonizado (minúsculas) por RegisterRequest. GET_LOCK admite hasta 64
        // caracteres: 'gintly_reg_' (11) + 36 = 47. Se recorta por defensa ante una clave anómala.
        return mb_substr('gintly_reg_'.$idempotencyKey, 0, 64);
    }

    /**
     * Adquiere el lock con nombre. Devuelve true SOLO si GET_LOCK confirma (1); false ante timeout (0) o
     * error/NULL. La espera es FINITA y acotada ([1, 60] s): nunca infinita (GET_LOCK con timeout negativo
     * espera para siempre). SQL parametrizado. Se fuerza el PDO de ESCRITURA para garantizar que el lock y la
     * transacción de escritura compartan la MISMA sesión MySQL aun si se añadiera una réplica de lectura.
     */
    private function acquireLock(string $name): bool
    {
        $timeout = min(60, max(1, (int) config('gintly.registration.lock_timeout_seconds', 10)));

        try {
            $row = DB::select('SELECT GET_LOCK(?, ?) AS locked', [$name, $timeout], false);
            $value = $row[0]->locked ?? null;

            // 1 = adquirido; 0 = timeout; NULL = error. Solo 1 es éxito.
            return $value !== null && (int) $value === 1;
        } catch (\Throwable $e) {
            // Error de infraestructura al intentar el lock: NO se trata como adquisición.
            report($e);

            return false;
        }
    }

    private function releaseLock(string $name): void
    {
        try {
            // Misma sesión (PDO de escritura) que la adquisición.
            DB::select('SELECT RELEASE_LOCK(?)', [$name], false);
        } catch (\Throwable $e) {
            report($e); // Nunca romper el flujo por liberar el lock.
        }
    }
}
