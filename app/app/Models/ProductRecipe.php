<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


// Línea de receta. Tabla puente con auto-referencia doble a
// products (compound_id / ingredient_id).
final class ProductRecipe extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'compound_id',
        'ingredient_id',
        'quantity',
        'unit_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

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
