<?php

declare(strict_types=1);

namespace Tests\Feature\Mod02;

use App\Exceptions\CyclicReferenceException;
use App\Models\Category;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

/**
 * MOD-02 sin base de datos:
 *  - Un ciclo de categorías/recetas (CyclicReferenceException) se renderiza como
 *    422/ERR-02 (contrato), no como 500. Antes la excepción no tenía render ni
 *    mapeo y degradaba a error interno.
 *  - El modo árbol expone la relación recursiva usada por ?tree=true.
 *
 * La verificación de comportamiento (ciclo real detectado por el servicio,
 * árbol anidado con datos) depende de MySQL y queda pendiente.
 */
final class CyclicReferenceRenderingTest extends TestCase
{
    public function test_ciclo_se_renderiza_como_422_err02(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            CyclicReferenceException::forCategory(5, 9)
        );

        $this->assertSame(422, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('ERR-02', $payload['code'] ?? null);
        $this->assertArrayHasKey('message', $payload);
    }

    public function test_ciclo_de_receta_tambien_es_422(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $this->app['request'],
            CyclicReferenceException::forRecipe(3, 3)
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_category_expone_relacion_recursiva_para_el_arbol(): void
    {
        $this->assertTrue(method_exists(Category::class, 'childrenRecursive'));
    }
}
