<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// ERR-02 · HTTP 422. Un parentesco de categorías o una receta introduciría
// un ciclo directo o indirecto.
final class CyclicReferenceException extends RuntimeException
{
    public static function forCategory(int $categoryId, int $parentId): self
    {
        return new self(
            "Asignar la categoría {$parentId} como padre de {$categoryId} "
            .'generaría un ciclo en la jerarquía.'
        );
    }

    public static function forRecipe(int $compoundId, int $ingredientId): self
    {
        return new self(
            "Incluir el producto {$ingredientId} en la receta de {$compoundId} "
            .'generaría un ciclo de composición.'
        );
    }
}

