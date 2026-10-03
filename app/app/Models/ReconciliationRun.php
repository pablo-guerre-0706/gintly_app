<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconciliationRunType;
use App\Enums\ReconciliationScope;
use App\Enums\ReconciliationStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReconciliationRun extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null; // Solo created_at (diccionario).

    protected $fillable = [
        'branch_id',
        'scope',
        'run_type',
    ];
    // FUERA de fillable: triggered_by, status, anomalies_found, started_at, finished_at.

    protected function casts(): array
    {
        return [
            'run_type'        => ReconciliationRunType::class,
            'scope'           => ReconciliationScope::class,
            'status'          => ReconciliationStatus::class,
            'anomalies_found' => 'integer',
            'started_at'      => 'immutable_datetime',
            'finished_at'     => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo      { return $this->belongsTo(Branch::class); }
    public function triggeredBy(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by'); }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }
}
