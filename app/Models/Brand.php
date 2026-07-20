<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // business() del trait. brand_id es nullOnDelete → no requiere guarda RESTRICT.

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
