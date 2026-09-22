<?php

declare(strict_types=1);

namespace Tests\Feature\Mod02;

use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Scopes\BusinessScope;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Binding anidado /products/{compound}/recipe/{line}.
 *
 * El scoped binding de Laravel resuelve el hijo por la relación
 * Str::plural(Str::camel('<param>')) sobre el padre. El parámetro de la línea es
 * 'line' → relación 'lines()'. Antes no existía y show/update/destroy lanzaban 500
 * sin acotar nada. Ahora Product::lines() apunta a las líneas de ESE compuesto
 * (compound_id) y ProductRecipe lleva BusinessScope, de modo que la línea queda
 * acotada al compuesto de la ruta y al negocio autenticado.
 *
 * Sin base de datos: valida la forma del binding, no la ejecución de la consulta.
 */
final class RecipeScopedBindingTest extends TestCase
{
    public function test_el_parametro_line_mapea_a_una_relacion_existente(): void
    {
        // Nombre de relación que Laravel buscará para el parámetro de ruta 'line'.
        $relationName = Str::plural(Str::camel('line'));

        $this->assertSame('lines', $relationName);
        $this->assertTrue(
            method_exists(Product::class, $relationName),
            'Product debe exponer la relación que el scoped binding resuelve para {line}.'
        );
    }

    public function test_lines_acota_por_compound_id(): void
    {
        $relation = (new Product())->lines();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(ProductRecipe::class, $relation->getRelated());
        // La FK garantiza que la línea pertenece al compuesto de la ruta.
        $this->assertSame('compound_id', $relation->getForeignKeyName());
    }

    public function test_productrecipe_aisla_por_negocio(): void
    {
        // El BusinessScope garantiza la segunda condición: pertenencia al tenant.
        $this->assertArrayHasKey(
            BusinessScope::class,
            (new ProductRecipe())->getGlobalScopes()
        );
    }
}
