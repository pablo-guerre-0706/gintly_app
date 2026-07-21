<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyEvent extends Model
{
    use HasFactory, BelongsToBusiness, Immutable;

    const UPDATED_AT = null;

    protected $fillable = [
        'anomaly_id', 'user_id', 'from_status', 'to_status', 'comment', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    // business() del trait; inmutabilidad del trait Immutable.
    public function anomaly(): BelongsTo { return $this->belongsTo(Anomaly::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
