<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


final class Category extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Categorías activas.
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // Categorías raíz (sin padre): punto de entrada del modo árbol.
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    //True si la categoría tiene subcategorías o productos: bloquea el
    //force-delete. El soft-delete siempre se permite.
    public function hasDependents(): bool
    {
        return $this->children()->exists() || $this->products()->exists();
    }
}
