<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta CADA petición autenticada (API y web) de cuentas que no deben operar endpoints humanos,
 * aunque su sesión siga vigente (auth:sanctum / guard web aún resuelven al usuario mientras la
 * sesión no expire):
 *   - cuentas DESACTIVADAS (is_active=false);
 *   - cuentas con ROL-SYS (actor de procesos automáticos, jamás usuario humano).
 *
 * Al detectarlas cierra e INVALIDA la sesión actual de forma segura (logout del guard web +
 * invalidación + regeneración del token CSRF) y responde 403. No redirige (evita bucles de
 * login en web) y mantiene JSON estable en API. La destrucción de la sesión aquí hace innecesario
 * el logout explícito de estas cuentas: la sesión ya queda inutilizable en la primera petición.
 */
final class EnsureOperableUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && (! $user->is_active || $user->hasSystemRole())) {
            $message = $user->hasSystemRole()
                ? 'La cuenta de sistema (ROL-SYS) no puede operar endpoints de usuario.'
                : 'Su cuenta está desactivada.';

            $this->terminateSession($request);

            abort(403, $message);
        }

        return $next($request);
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
