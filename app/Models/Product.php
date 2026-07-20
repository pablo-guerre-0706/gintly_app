<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Exceptions\ImmutableSkuException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'category_id',      // validar tenant en FormRequest
        'brand_id',
        'unit_id',
        'sku',
        'name',
        'type',
        'sale_price',
        'cost',
        'tracks_inventory',
        'is_taxable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type'             => ProductType::class,
            'sale_price'       => 'decimal:2',   // string: precisión monetaria
            'cost'             => 'decimal:2',
            'tracks_inventory' => 'boolean',
            'is_taxable'       => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // RF-02-04 (FIX auditoría #3): un servicio NUNCA rastrea inventario.
        // Comparación contra la INSTANCIA del enum, no contra el string.
        static::saving(function (Product $product): void {
            if ($product->type === ProductType::Service) {
                $product->tracks_inventory = false;
            }
        });

        // RF-02-04: SKU inmutable una vez el producto tenga transacciones.
        // Hook activo; hasTransactions() se cablea en MOD-03/MOD-07.
        static::updating(function (Product $product): void {
            if ($product->isDirty('sku') && $product->hasTransactions()) {
                throw new ImmutableSkuException;
            }
        });
    }

    /** TODO (MOD-03/MOD-07): contar inventory_movements y sale_items. Inerte por ahora. */
    public function hasTransactions(): bool
    {
        return false;
    }

    // business() del trait

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    /** Líneas de receta donde ESTE producto es el compuesto. */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(ProductRecipe::class, 'compound_id');
    }

    /** Insumos que lo componen (azúcar sobre la tabla puente). */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_recipes', 'compound_id', 'ingredient_id')
            ->withPivot(['quantity', 'unit_id']);
    }

    /** Recetas donde ESTE producto se usa como insumo. */
    public function usedAsIngredientIn(): HasMany
    {
        return $this->hasMany(ProductRecipe::class, 'ingredient_id');
    }
}
