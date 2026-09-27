<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Auditoría global · Integridad del registro de rutas y del flujo web de clientes.
 *
 * Regresión de dos defectos detectados en la auditoría global:
 *  (1) Dos rutas compartían el nombre 'customers.index' (recurso API vs. vista web del panel), lo que
 *      ROMPÍA `php artisan route:cache`. Se renombró la vista web a 'web.customers.index'.
 *  (2) La vista customers/index.blade.php enlazaba a route('customers.view.create'), un nombre
 *      INEXISTENTE → RouteNotFoundException (HTTP 500 al renderizar). Se registró GET /customers/create
 *      como 'web.customers.create' y se corrigió el enlace.
 *
 * No requiere MySQL: valida el registro de rutas y renderiza las vistas del panel con un usuario
 * autenticado en memoria (sin persistencia) y Vite deshabilitado. Corre en ambas suites.
 */
final class RouteIntegrityTest extends TestCase
{
    private function panelUser(): User
    {
        $user = new User();
        $user->forceFill([
            'id'          => 1,
            'name'        => 'Auditor',
            'email'       => 'auditor@test.local',
            'business_id' => 1,
            'is_active'   => true,
        ]);

        return $user;
    }

    public function test_no_hay_nombres_de_ruta_duplicados(): void
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name !== null && $name !== '') {
                $names[] = $name;
            }
        }

        $counts = array_count_values($names);
        $duplicates = array_keys(array_filter($counts, static fn (int $c): bool => $c > 1));

        $this->assertSame(
            [],
            $duplicates,
            'Nombres de ruta duplicados (rompen route:cache): '.implode(', ', $duplicates)
        );
    }

    public function test_web_customers_index_resuelve_get_customers(): void
    {
        $route = Route::getRoutes()->getByName('web.customers.index');

        $this->assertNotNull($route, 'Falta la ruta web.customers.index.');
        $this->assertSame('customers', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    public function test_web_customers_create_resuelve_get_customers_create(): void
    {
        $route = Route::getRoutes()->getByName('web.customers.create');

        $this->assertNotNull($route, 'Falta la ruta web.customers.create.');
        $this->assertSame('customers/create', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    public function test_referencias_de_navegacion_no_producen_route_not_found(): void
    {
        // Todos los nombres que usan las vistas de clientes y su layout de panel deben existir.
        foreach ([
            'web.customers.index', 'web.customers.create', 'customers.index',
            'dashboard', 'login', 'catalog.products',
        ] as $name) {
            $this->assertIsString(
                route($name, [], false),
                "route('{$name}') lanzaría RouteNotFoundException."
            );
        }
    }

    public function test_usuario_autenticado_renderiza_paginas_web_de_clientes(): void
    {
        $this->withoutVite();
        $user = $this->panelUser();

        $this->actingAs($user, 'web')->get('/customers')->assertOk();
        $this->actingAs($user, 'web')->get('/customers/create')->assertOk();
    }

    public function test_las_rutas_de_api_estan_bajo_el_prefijo_v1(): void
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/') && ! str_starts_with($uri, 'api/v1')) {
                $this->fail("Ruta API fuera de api/v1: {$uri}");
            }
        }

        $this->assertTrue(true);
    }

    public function test_recurso_api_customers_permanece_sin_cambios_contractuales(): void
    {
        // El apiResource de clientes conserva sus URIs/nombres estándar bajo api/v1 (contrato intacto).
        $expected = [
            'customers.index'   => 'api/v1/customers',
            'customers.store'   => 'api/v1/customers',
            'customers.show'    => 'api/v1/customers/{customer}',
            'customers.update'  => 'api/v1/customers/{customer}',
            'customers.destroy' => 'api/v1/customers/{customer}',
        ];

        foreach ($expected as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Falta la ruta API {$name}.");
            $this->assertSame($uri, $route->uri());
        }
    }
}
