<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\SubscriptionRequiredException;
use App\Models\User;
use App\Services\Billing\CommercialAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compuerta COMERCIAL central: tras autenticar y verificar operabilidad, exige que el negocio del usuario
 * tenga una suscripción pagada VIGENTE para operar los módulos del ERP. ROL-01 NO queda exento. Es una
 * condición ADICIONAL: no reemplaza rol/perfil/sucursal/tenant. Se aplica a las rutas operativas (no a
 * identidad /me, salida /logout, cambio de contraseña propia ni a /billing de contratación).
 */
final class EnsureActiveSubscription
{
    public function __construct(private readonly CommercialAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Sin usuario autenticado, el control de sesión (auth) ya respondió; aquí no se decide.
        if ($user instanceof User && ! $this->access->grantsAccess((int) $user->business_id)) {
            throw new SubscriptionRequiredException();
        }

        return $next($request);
    }
}
