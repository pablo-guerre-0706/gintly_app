<?php

namespace App\Models;

use App\Exceptions\CyclicReferenceException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecipe extends Model
{
    use HasFactory, BelongsToBusiness;

    // Tabla puente sin columnas de tiempo (según el .md).
    public $timestamps = false;

    protected $fillable = [
        'compound_id',
        'ingredient_id',
        'quantity',
        'unit_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductRecipe $recipe): void {
            $compound   = (int) $recipe->compound_id;
            $ingredient = (int) $recipe->ingredient_id;

            // 1) Auto-composición directa (respaldo en app del CHECK chk_recipe_no_self).
            if ($compound === $ingredient) {
                throw new CyclicReferenceException(
                    'Un producto compuesto no puede contenerse a sí mismo.'
                    );
            }

            // 2) Ciclo indirecto: la arista compound→ingredient cierra ciclo
            //    si YA existe una ruta ingredient→…→compound.
            if (self::pathExists($ingredient, $compound)) {
                throw new CyclicReferenceException(
                    'La receta genera un ciclo: el insumo depende del compuesto.'
                    );
            }
        });
    }

    /** DFS por el grafo de recetas (compound→ingredient) desde $from buscando $target. */
    protected static function pathExists(int $from, int $target): bool
    {
        $stack   = [$from];
        $visited = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $target) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            // Insumos del producto actual (si actúa como compuesto en alguna receta).
            $children = static::query()->where('compound_id', $current)->pluck('ingredient_id');
            foreach ($children as $child) {
                $stack[] = (int) $child;
            }
        }

        return false;
    }

    // business() del trait

    public function compound(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'compound_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }
}
