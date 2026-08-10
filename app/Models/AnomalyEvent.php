<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AnomalyEvent extends Model
{
    use BelongsToBusiness;
    use Immutable; // Bitácora append-only: bloquea UPDATE/DELETE.

    public const UPDATED_AT = null; // Solo created_at.

    protected $fillable = [
        'anomaly_id',
        'user_id',
        'from_status',
        'to_status',
        'comment',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'immutable_datetime',
        ];
    }

    public function anomaly(): BelongsTo { return $this->belongsTo(Anomaly::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
