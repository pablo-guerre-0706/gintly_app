<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Exceptions\CyclicReferenceException;
use App\Exceptions\RestrictDeleteException;
use App\Models\Category;
use Illuminate\Support\Facades\DB;



final class CategoryService
{
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data): Category {
            if (! empty($data['parent_id'])) {
                // En create el nodo aún no existe: basta con verificar que el
                // padre elegido no cuelgue de una cadena rota. No hay ciclo
                // posible todavía, pero validamos que el padre exista y esté
                // activo bajo lock para serializar contra ediciones concurrentes.
                Category::query()
                    ->whereKey($data['parent_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            return Category::query()->create($data);
        });
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data): Category {
            if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
                $this->assertNoCycle($category->getKey(), (int) $data['parent_id']);
            }

            $category->update($data);

            return $category->refresh();
        });
    }

    private function assertNoCycle(int $nodeId, int $proposedParentId): void
    {
        if ($nodeId === $proposedParentId) {
            throw CyclicReferenceException::forCategory($nodeId, $proposedParentId);
        }

        $cursor = $proposedParentId;
        $visited = [];

        while ($cursor !== null) {
            if ($cursor === $nodeId) {
                throw CyclicReferenceException::forCategory($nodeId, $proposedParentId);
            }

            // Guarda anti-bucle-infinito ante datos ya corruptos.
            if (isset($visited[$cursor])) {
                break;
            }
            $visited[$cursor] = true;

            $parent = Category::query()
                ->whereKey($cursor)
                ->lockForUpdate()
                ->value('parent_id');

            $cursor = $parent !== null ? (int) $parent : null;
        }
    }

    // Soft-delete siempre permitido. El force-delete se bloquea si hay
    // subcategorías o productos (ERR-02B).
    public function delete(Category $category, bool $force = false): void
    {
        DB::transaction(function () use ($category, $force): void {
            if ($force && $category->hasDependents()) {
                throw RestrictDeleteException::make(
                    'la categoría',
                    'subcategorías o productos'
                );
            }

            $force ? $category->forceDelete() : $category->delete();
        });
    }
}
