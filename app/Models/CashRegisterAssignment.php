<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-06 · Asignación Caja–Cajero (historial temporal). Una fila ACTIVA (ended_at NULL) vincula la caja
 * a su cajero. No es Immutable: finalizar fija ended_at/ended_by UNA vez (cierre de vigencia); el resto
 * del historial no se toca ni se borra. Los candados de motor garantizan una activa por caja y por cajero.
 */
final class CashRegisterAssignment extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'branch_id',
        'cash_register_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'ended_at',
        'ended_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at'    => 'datetime',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
