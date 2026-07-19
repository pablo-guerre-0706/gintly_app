<?php

namespace App\Models;

use App\Exceptions\InmutableAuditException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory, BelongsToBusiness;

    // Bitácora append-only: no hay updated_at en el esquema.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    // Guarda de inmutabilidad a nivel de modelo (además de la Policy).
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InmutableAuditException;
        });

        static::deleting(function (): void {
            throw new InmutableAuditException(
                'Los registros de auditoría no pueden eliminarse.'
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // morph manual: columnas auditable_type / auditable_id
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
