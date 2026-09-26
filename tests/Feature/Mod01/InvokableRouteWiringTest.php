<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Http\Controllers\Api\V1\UpdatePasswordController;
use App\Http\Controllers\Api\V1\UpdateUserEmailController;
use App\Http\Controllers\Api\V1\UpdateUserPasswordController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regresión MOD-01: los controladores de contraseña/correo son invocables
 * (solo definen __invoke). Antes las rutas apuntaban a un método `update`
 * inexistente, de modo que PUT /me/password, /users/{user}/password y
 * /users/{user}/email respondían 500 (BadMethodCallException).
 *
 * No requiere base de datos: solo inspecciona la tabla de rutas.
 */
final class InvokableRouteWiringTest extends TestCase
{
    /**
     * @return array<string, array{string, string, class-string}>
     */
    public static function invokableRoutes(): array
    {
        return [
            'PUT /me/password' => ['PUT', 'api/v1/me/password', UpdatePasswordController::class],
            'PUT /users/{user}/password' => ['PUT', 'api/v1/users/{user}/password', UpdateUserPasswordController::class],
            'PUT /users/{user}/email' => ['PUT', 'api/v1/users/{user}/email', UpdateUserEmailController::class],
        ];
    }

    /**
     * @param  class-string  $controller
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invokableRoutes')]
    public function test_ruta_resuelve_a_controlador_invocable(string $method, string $uri, string $controller): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === $uri && in_array($method, $r->methods(), true));

        $this->assertNotNull($route, "La ruta {$method} {$uri} no está registrada.");

        $action = $route->getActionName();

        // Un controlador invocable referenciado por clase queda como 'Clase'
        // (Laravel resuelve __invoke); nunca debe apuntar a 'Clase@update'.
        $this->assertContains($action, [$controller, $controller.'@__invoke'],
            "La ruta {$method} {$uri} debe apuntar al controlador invocable {$controller}, no a un método inexistente."
        );
        $this->assertStringNotContainsString('@update', $action,
            "La ruta {$method} {$uri} no debe referenciar un método update() inexistente."
        );

        $this->assertTrue(method_exists($controller, '__invoke'),
            "{$controller} debe definir __invoke."
        );
        $this->assertFalse(method_exists($controller, 'update'),
            "{$controller} no define update(): referenciarlo rompería la ruta."
        );
    }
}
