<?php

namespace App\Models;

use App\Enums\ReconciliationRunType;
use App\Enums\ReconciliationScope;
use App\Enums\ReconciliationStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    use HasFactory, BelongsToBusiness;

    const UPDATED_AT = null;   // el .md: solo created_at

    protected $fillable = [
        'branch_id', 'triggered_by', 'run_type', 'scope', 'status',
        'anomalies_found', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'run_type'        => ReconciliationRunType::class,
            'scope'           => ReconciliationScope::class,
            'status'          => ReconciliationStatus::class,
            'anomalies_found' => 'integer',
            'started_at'      => 'datetime',
            'finished_at'     => 'datetime',
            'created_at'      => 'immutable_datetime',
        ];
    }

    // business() del trait
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function triggeredBy(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by'); }
    public function anomalies(): HasMany { return $this->hasMany(Anomaly::class); }
}
