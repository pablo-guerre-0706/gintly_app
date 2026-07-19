<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'name',
        'address',
        'manager_user_id',  // validar tenant en FormRequest
        'opened_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // business() proviene del trait BelongsToBusiness

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
