<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Models\User;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Invalidación de sesiones tras cambio de credenciales.
 *
 * Cobertura sin base de datos: verifica que el servicio es consciente del driver
 * configurado y que, con un driver que no es `database`, no consulta ninguna
 * tabla y devuelve 0 (no-op) en vez de fallar. El borrado real de filas sobre el
 * driver `database` depende de MySQL y queda como verificación pendiente.
 */
final class SessionInvalidatorTest extends TestCase
{
    public function test_es_noop_cuando_el_driver_no_es_database(): void
    {
        Config::set('session.driver', 'array');

        $user = new User();
        $user->id = 1;

        $invalidator = $this->app->make(SessionInvalidator::class);

        $this->assertSame(0, $invalidator->flushAllForUser($user));
        $this->assertSame(0, $invalidator->flushOtherSessionsForUser($user, 'sesion-actual'));
    }

    public function test_userservice_recibe_el_invalidador_de_sesiones(): void
    {
        $ctor = (new \ReflectionClass(\App\Services\Users\UserService::class))->getConstructor();
        $this->assertNotNull($ctor);

        $types = array_map(
            fn ($p) => $p->getType()?->getName(),
            $ctor->getParameters()
        );

        $this->assertContains(SessionInvalidator::class, $types);
    }
}
