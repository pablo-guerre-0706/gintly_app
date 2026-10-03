<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\ProductType;
use App\Exceptions\CyclicReferenceException;
use App\Models\Product;
use App\Models\ProductRecipe;
use Illuminate\Support\Facades\DB;


final class RecipeService
{
    public function addLine(Product $compound, array $data): ProductRecipe
    {
        return DB::transaction(function () use ($compound, $data): ProductRecipe {
            $ingredientId = (int) $data['ingredient_id'];

            $this->assertNoCompositionCycle($compound->getKey(), $ingredientId);

            return ProductRecipe::query()->create([
                'compound_id'   => $compound->getKey(),
                'ingredient_id' => $ingredientId,
                'quantity'      => $data['quantity'],
                'unit_id'       => $data['unit_id'],
            ]);
        });
    }

    public function updateLine(ProductRecipe $line, array $data): ProductRecipe
    {
        return DB::transaction(function () use ($line, $data): ProductRecipe {
            // El insumo no es editable (ver UpdateRecipeLineRequest), así que no
            // hay que reanalizar el ciclo: solo cambian cantidad y unidad.
            $line->update($data);

            return $line->refresh();
        });
    }

    public function deleteLine(ProductRecipe $line): void
    {
        $line->delete();
    }

    private function assertNoCompositionCycle(int $compoundId, int $ingredientId): void
    {
        // chk_recipe_no_self: auto-composición directa.
        if ($compoundId === $ingredientId) {
            throw CyclicReferenceException::forRecipe($compoundId, $ingredientId);
        }

        // Solo un compuesto puede tener sub-insumos; si el ingrediente no lo es,
        // no hay descenso posible y por tanto no hay ciclo.
        $ingredientType = Product::query()
            ->whereKey($ingredientId)
            ->value('type');

        if ($ingredientType !== ProductType::Compound) {
            return;
        }

        $stack = [$ingredientId];
        $visited = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $compoundId) {
                throw CyclicReferenceException::forRecipe($compoundId, $ingredientId);
            }

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            // Insumos del compuesto actual, bajo lock para serializar contra
            // ediciones de receta concurrentes.
            $children = ProductRecipe::query()
                ->where('compound_id', $current)
                ->lockForUpdate()
                ->pluck('ingredient_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            foreach ($children as $child) {
                $stack[] = $child;
            }
        }
    }
}

