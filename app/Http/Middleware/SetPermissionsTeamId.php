<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;


// Configura el aislamiento multi-tenant de Spatie usando business_id, evitando fallo
// silencioso (403 generalizado) al asegurar que las políticas no evaluen contra un equipo nulo.
// La inyección en el constructor optimiza el rendimiento al reutilizar la instancia del PermissionRegistrar.
final class SetPermissionsTeamId
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {
    }

    /**
     * @param  string|null  $guard  Guard explícito. Permite invocarlo como
     *                              'tenant.permissions:web' en rutas que no
     *                              usen el guard de API.
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $user = $this->resolveUser($request, $guard);

        $businessId = $user?->getAttribute('business_id');

        // Se asigna siempre, incluso como null, para evitar la fuga de datos entre
        // negocios bajo servidores persistentes (como Octane), previniendo que el ID del
        // cliente anterior persista.
        $this->registrar->setPermissionsTeamId(
            $businessId !== null ? (int) $businessId : null
        );

        return $next($request);
    }

    
    // Limpia el ID del negocio tras la respuesta para evitar fugas de datos en servidores persistentes.
    public function terminate(Request $request, Response $response): void
    {
        $this->registrar->setPermissionsTeamId(null);
    }


    // Como el grupo api corre antes de auth:sanctum, el usuario aún no está autenticado.
    // Debes buscarlo directamente en el guard del token para evitar que devuelva nulo.    
    private function resolveUser(Request $request, ?string $guard): ?Authenticatable
    {
        if ($guard !== null) {
            return $request->user($guard);
        }

        foreach ($this->guards() as $candidate) {
            if (($user = $request->user($candidate)) !== null) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function guards(): array
    {
        return (array) config('gintly.tenant.guards', ['sanctum']);
    }
}
