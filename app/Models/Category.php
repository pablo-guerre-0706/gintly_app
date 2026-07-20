<?php

namespace App\Models;

use App\Exceptions\CyclicReferenceException;
use App\Exceptions\RestrictDeleteException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'parent_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // anti-ciclo. El padre no puede ser el propio nodo ni un descendiente.
        static::saving(function (Category $category): void {
            if ($category->parent_id === null) {
                return;
            }

            $selfId   = $category->getKey();       // null si es nuevo → no hay ciclo posible
            $ancestor = (int) $category->parent_id;
            $guard    = 0;                          // cinturón anti-bucle infinito

            while ($ancestor !== 0 && $guard++ < 1000) {
                if ($selfId !== null && $ancestor === (int) $selfId) {
                    throw new CyclicReferenceException('Una categoría no puede ser descendiente de sí misma.');
                }
                // Subimos por la cadena del padre propuesto. value() respeta el scope de tenant.
                $ancestor = (int) static::query()->whereKey($ancestor)->value('parent_id');
            }
        });

        // ERR-02B: bloqueo amigable del borrado fisico con dependencias (el soft-delete siempre pasa).
        static::deleting(function (Category $category): void {
            if ($category->isForceDeleting()
                && ($category->children()->exists() || $category->products()->exists())) {
                throw new RestrictDeleteException('La categoría tiene subcategorías o productos. Desactívela en su lugar.');
            }
        });
    }

    // business() proviene del trait

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
}
