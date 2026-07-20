<?php

namespace App\Models;

use App\Exceptions\RestrictDeleteException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use HasFactory, BelongsToBusiness;

    // la tabla no sigue la pluralización por defecto de Laravel.
    protected $table = 'units_of_measure';

    protected $fillable = [
        'name',
        'abbreviation',
    ];

    // Sin casts especiales: solo strings + timestamps estándar.

    protected static function booted(): void
    {
        // RF-02-03 / ERR-02B: sin softDeletes, cualquier delete es físico → guarda directa.
        static::deleting(function (UnitOfMeasure $unit): void {
            if ($unit->products()->exists() || $unit->recipeLines()->exists()) {
                throw new RestrictDeleteException('La unidad de medida está en uso por productos o recetas.');
            }
        });
    }

    // business() del trait

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(ProductRecipe::class, 'unit_id');
    }
}
