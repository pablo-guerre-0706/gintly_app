<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class Anomaly extends Model
{
    use BelongsToBusiness;

    // Todos los atributos los fija el Service (detección/transición). Sin mass-assignment.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'severity'       => AnomalySeverity::class,
            'status'         => AnomalyStatus::class,
            'expected_value' => 'decimal:2',
            'actual_value'   => 'decimal:2',
            'difference'     => 'decimal:2',
            'source_id'      => 'integer',
            'detected_at'    => 'immutable_datetime',
            'resolved_at'    => 'immutable_datetime',
        ];
    }

    // active_dedupe_key es columna generada: nunca escribible.
    public function getActiveDedupeKeyAttribute(mixed $value): ?string
    {
        return $value;
    }

    // ---------------- Relaciones ----------------
    public function rule(): BelongsTo              { return $this->belongsTo(AnomalyRule::class, 'anomaly_rule_id'); }
    public function reconciliationRun(): BelongsTo { return $this->belongsTo(ReconciliationRun::class); }
    public function branch(): BelongsTo            { return $this->belongsTo(Branch::class); }
    public function resolvedBy(): BelongsTo        { return $this->belongsTo(User::class, 'resolved_by'); }

    public function events(): HasMany
    {
        return $this->hasMany(AnomalyEvent::class)->orderBy('changed_at');
    }

    // ---------------- Predicados ----------------
    public function isActive(): bool   { return $this->status->isActive(); }
    public function canJustify(): bool { return $this->status->canJustify(); }
    public function canResolve(): bool { return $this->status->canResolve(); }

    /** Resuelve el puntero débil (source_type = tabla) a su registro origen. */
    public function resolveSource(): ?object
    {
        if ($this->source_type === null || $this->source_id === null) {
            return null;
        }

        return DB::table($this->source_type)
            ->where('id', $this->source_id)
            ->where('business_id', $this->business_id) // Aislamiento multi-tenant.
            ->first();
    }
}
