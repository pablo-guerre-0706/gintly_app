<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\RestrictDeleteException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class UnitOfMeasure extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'name',
        'abbreviation',
    ];

    protected static function booted(): void
    {
        // units_of_measure no tiene SoftDeletes y está protegida por RESTRICT.
        // La regla viaja con el dato: cualquier borrado (API, job, tinker) queda blindado.
        static::deleting(function (self $unit): void {
            if ($unit->hasDependents()) {
                throw new RestrictDeleteException(
                    'No se puede eliminar la unidad: existen productos o recetas que la referencian.',
                );
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(ProductRecipe::class, 'unit_id');
    }

    public function hasDependents(): bool
    {
        return $this->products()->exists() || $this->recipeLines()->exists();
    }
}
