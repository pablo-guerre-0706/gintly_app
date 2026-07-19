<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Observers\BusinessObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([BusinessObserver::class])]  // hook listo, se llena en MOD-05/MOD-11
class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',   // seguro aquí: businesses es la raíz, no hay fuga de tenant
        'plan',
        'status',
        'tax_rate',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'status'   => BusinessStatus::class,
            'tax_rate' => 'decimal:4',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}