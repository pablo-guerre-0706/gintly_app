<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta CADA petición autenticada (API y web) de cuentas o negocios que no deben operar endpoints
 * humanos, aunque la sesión (y su cookie) sigan vigentes (auth:sanctum / guard web aún resuelven al
 * usuario mientras la sesión no expire):
 *   - cuentas DESACTIVADAS (is_active=false);
 *   - cuentas con ROL-SYS (actor de procesos automáticos, jamás usuario humano);
 *   - negocios con SUSPENSIÓN ADMINISTRATIVA (BusinessStatus::Suspended) decretada DESPUÉS del login:
 *     se bloquea la operación aunque la cookie siga válida. Es un control INDEPENDIENTE de la
 *     suscripción comercial; un pago NUNCA lo levanta (lo retira un administrador cambiando el estado).
 *
 * Al detectarlas cierra e INVALIDA la sesión actual de forma segura (logout del guard web +
 * invalidación + regeneración del token CSRF) y responde 403. No redirige (evita bucles de
 * login en web) y mantiene JSON estable en API. La destrucción de la sesión aquí hace innecesario
 * el logout explícito de estas cuentas: la sesión ya queda inutilizable en la primera petición.
 *
 * NO afecta al aprovisionamiento canónico (registro público fuera de auth) ni a los procesos de
 * fondo (consola, sin sesión): solo intercepta peticiones humanas autenticadas.
 */
final class EnsureOperableUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            if (! $user->is_active || $user->hasSystemRole() || $this->businessSuspended($user)) {
                $message = match (true) {
                    $user->hasSystemRole() => 'La cuenta de sistema (ROL-SYS) no puede operar endpoints de usuario.',
                    ! $user->is_active     => 'Su cuenta está desactivada.',
                    default                => 'El negocio está suspendido administrativamente.',
                };

                $this->terminateSession($request);

                abort(403, $message);
            }
        }

        return $next($request);
    }

    /**
     * ¿El negocio del usuario está en suspensión administrativa (no puede operar)? Se resuelve de forma
     * defensiva: si la tabla de negocios no es resoluble (p. ej. smoke tests de rutas con un usuario en memoria
     * y sin persistencia), NO se afirma suspensión (los demás controles siguen aplicando). En producción la
     * tabla siempre está disponible y la suspensión se evalúa con normalidad.
     */
    private function businessSuspended(User $user): bool
    {
        try {
            $business = $user->business;
        } catch (QueryException) {
            return false;
        }

        return $business !== null && ! $business->status->canOperate();
    }

    /**
     * Cierra la sesión de forma segura sin provocar bucles de redirección: destruye la sesión
     * persistida (si existe) y desautentica el guard web. Peticiones API sin sesión (stateless)
     * simplemente no tienen nada que invalidar; el 403 posterior las bloquea igual.
     */
    private function terminateSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        try {
            Auth::guard('web')->logout();
        } catch (\Throwable) {
            // Guard sin sesión asociada: nada que cerrar.
        }

        $session = $request->session();
        $session->invalidate();
        $session->regenerateToken();
    }
}
