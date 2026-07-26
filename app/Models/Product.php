<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


// Ítem de catálogo. type unifica simple/compound/service.
final class Product extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
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
            'sale_price'       => 'decimal:2',
            'cost'             => 'decimal:2',
            'tracks_inventory' => 'boolean',
            'is_taxable'       => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    // Coerción universal: un servicio nunca rastrea inventario, venga la
    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->type === ProductType::Service) {
                $product->tracks_inventory = false;
            }
        });
    }

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

    // Líneas de receta donde este producto es el compuesto (lo que se arma).
    public function recipeLines(): HasMany
    {
        return $this->hasMany(ProductRecipe::class, 'compound_id');
    }

    // Líneas donde este producto figura como insumo de otros compuestos.
    public function usedInRecipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class, 'ingredient_id');
    }

    // Insumos de este compuesto, vía tabla puente product_recipes.
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_recipes', 'compound_id', 'ingredient_id')
            ->withPivot(['quantity', 'unit_id']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, ProductType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * H-15 · Verdadero si el SKU ya no puede cambiar por existir transacciones.
     *
     * En MOD-02 ninguna de esas tablas transaccionales existe todavía, así que
     * este método devuelve false. Los checks se activan (parche P4) al cerrar
     * MOD-07, descomentando las tres comprobaciones. Se deja el andamiaje
     * explícito para que el guardián del FormRequest ya lo invoque sin cambios.
     */
    public function hasTransactions(): bool
    {
        // Activar en P4 (cierre de MOD-07):
        // return $this->inventoryMovements()->exists()
        //     || $this->saleItems()->exists()
        //     || $this->purchaseOrderItems()->exists();

        return false;
    }
}
