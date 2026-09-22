<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Invalidación de sesiones de un usuario tras un cambio de credenciales
 * (contraseña o correo), acorde al controlador de sesiones configurado.
 *
 * El despliegue usa el driver `database` (config/session.php → 'driver'), por lo
 * que las sesiones viven en la tabla `sessions` con columna `user_id`: borrar la
 * fila invalida la sesión de inmediato (el id deja de resolverse). Otros drivers
 * (file/redis/cookie/array) no permiten enumerar por usuario desde aquí; en ese
 * caso el método es un no-op explícito y devuelve 0.
 */
final class SessionInvalidator
{
    /**
     * Cierra TODAS las sesiones del usuario (reseteo administrativo de clave o
     * cambio de correo del usuario destino).
     */
    public function flushAllForUser(User $user): int
    {
        $query = $this->sessionsQuery();

        if ($query === null) {
            return 0;
        }

        return $query->where('user_id', $user->getKey())->delete();
    }

    /**
     * Cierra las DEMÁS sesiones del usuario y conserva la actual (autoservicio
     * /me/password: el titular no pierde su sesión vigente).
     */
    public function flushOtherSessionsForUser(User $user, ?string $keepSessionId): int
    {
        $query = $this->sessionsQuery();

        if ($query === null) {
            return 0;
        }

        $query->where('user_id', $user->getKey());

        if ($keepSessionId !== null && $keepSessionId !== '') {
            $query->where('id', '!=', $keepSessionId);
        }

        return $query->delete();
    }

    /**
     * Constructor de consulta sobre el almacén de sesiones, o null si el driver
     * configurado no es `database` (no hay tabla que recorrer por usuario).
     */
    private function sessionsQuery(): ?\Illuminate\Database\Query\Builder
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        return DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'));
    }
}
