<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperativeProfile;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fase 3 · Asignación persistente de un perfil operativo a un usuario ROL-03.
 * Normalizada (una fila por perfil), aislada por negocio y auditable (assigned_by).
 */
final class UserOperativeProfile extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'user_id',
        'profile',
        'assigned_by',
    ];
    // business_id fuera de fillable: lo fija el trait/Service desde la sesión.

    protected function casts(): array
    {
        return [
            'profile' => OperativeProfile::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
