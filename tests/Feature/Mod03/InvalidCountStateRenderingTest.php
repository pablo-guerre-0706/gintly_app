<?php

declare(strict_types=1);

namespace Tests\Feature\Mod03;

use App\Exceptions\InvalidCountStateException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

/**
 * MOD-03 sin base de datos.
 *
 * Aplicar/justificar un conteo que ya no está abierto, o completar/cancelar un
 * traspaso que ya no está pendiente, debe responder 409 (contrato). Antes la
 * excepción no tenía render ni mapeo y degradaba a 500.
 *
 * La verificación de comportamiento (transición real de estado) depende de MySQL
 * y queda pendiente.
 */
final class InvalidCountStateRenderingTest extends TestCase
{
    public function test_conteo_no_abierto_es_409(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            InvalidCountStateException::countNotOpen(7)
        );

        $this->assertSame(409, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('INVALID_STATE', $payload['error'] ?? null);
        $this->assertArrayHasKey('message', $payload);
    }

    public function test_traspaso_no_pendiente_es_409(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            InvalidCountStateException::transferNotPending(3)
        );

        $this->assertSame(409, $response->getStatusCode());
    }
}
