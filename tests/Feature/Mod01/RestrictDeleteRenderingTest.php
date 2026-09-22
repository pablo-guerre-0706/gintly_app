<?php

declare(strict_types=1);

namespace Tests\Feature\Mod01;

use App\Exceptions\RestrictDeleteException;
use App\Models\Branch;
use App\Services\Audit\AuditLogger;
use App\Services\Users\UserService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

/**
 * Cierre de MOD-01 sin base de datos:
 *  - ERR-02B (baja de sucursal con dependientes) se renderiza como 409, no 500.
 *  - El cableado de auditoría (AuditLogger inyectado, guarda de dependientes)
 *    queda presente para que un refactor no lo elimine en silencio.
 *
 * La verificación de comportamiento con datos reales (rows en audit_logs, 409
 * efectivo sobre una sucursal poblada) depende de MySQL y queda pendiente.
 */
final class RestrictDeleteRenderingTest extends TestCase
{
    public function test_restrict_delete_se_renderiza_como_409_err02b(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            RestrictDeleteException::make('la sucursal', 'bodegas, cajas o usuarios')
        );

        $this->assertSame(409, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('ERR-02B', $payload['code'] ?? null);
        $this->assertArrayHasKey('message', $payload);
    }

    public function test_branch_expone_la_guarda_de_dependientes(): void
    {
        $this->assertTrue(method_exists(Branch::class, 'hasOperationalDependents'));
        $this->assertTrue(method_exists(Branch::class, 'warehouses'));
        $this->assertTrue(method_exists(Branch::class, 'cashRegisters'));
    }

    public function test_los_servicios_de_identidad_reciben_el_auditlogger(): void
    {
        // Se resuelven vía contenedor con su dependencia de auditoría cableada.
        $this->assertInstanceOf(UserService::class, $this->app->make(UserService::class));
        $this->assertInstanceOf(AuditLogger::class, $this->app->make(AuditLogger::class));

        $ctor = (new \ReflectionClass(UserService::class))->getConstructor();
        $this->assertNotNull($ctor, 'UserService debe declarar constructor con AuditLogger.');
        $this->assertSame(
            AuditLogger::class,
            $ctor->getParameters()[0]->getType()?->getName()
        );
    }
}
