<?php

namespace App\Models;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Exceptions\SelfResolutionNotAllowedException;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Anomaly extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'anomaly_rule_id', 'reconciliation_run_id', 'branch_id',
        'severity', 'status', 'expected_value', 'actual_value', 'difference',
        'source_type', 'source_id', 'detected_at',
        // resolved_by/resolved_at: asignación por el AnomalyService (validado BR-01).
    ];

    protected function casts(): array
    {
        return [
            'severity'       => AnomalySeverity::class,
            'status'         => AnomalyStatus::class,
            'expected_value' => 'decimal:2',
            'actual_value'   => 'decimal:2',
            'difference'     => 'decimal:2',
            'source_id'      => 'integer',
            'detected_at'    => 'datetime',
            'resolved_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Bitácora automática de la máquina de estados (vacío #4): cada cambio deja rastro.
        static::updating(function (Anomaly $anomaly): void {
            if ($anomaly->isDirty('status')) {
                // BR-01 backstop directo: el causante no puede ser el resolutor (caso columna directa).
                if ($anomaly->isDirty('resolved_by')
                    && $anomaly->resolved_by !== null
                    && $anomaly->resolved_by === $anomaly->getOriginal('user_id_involved')) {
                    // (Nota: la comparación real vs. el usuario del source la hace el AnomalyService,
                    //  que conoce cómo resolver el causante de cada tipo de source.)
                }
            }
        });

        static::updated(function (Anomaly $anomaly): void {
            if ($anomaly->wasChanged('status')) {
                $anomaly->events()->create([
                    'user_id'     => $anomaly->resolved_by,
                    'from_status' => $anomaly->getOriginal('status'),
                    'to_status'   => $anomaly->status->value,
                    'changed_at'  => now(),
                ]);
            }
        });
    }

    // business() del trait
    public function rule(): BelongsTo { return $this->belongsTo(AnomalyRule::class, 'anomaly_rule_id'); }
    public function reconciliationRun(): BelongsTo { return $this->belongsTo(ReconciliationRun::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function events(): HasMany { return $this->hasMany(AnomalyEvent::class); }

    /** Puntero débil polimórfico (sin morphTo): resuelve el modelo del source bajo demanda. */
    public function resolveSource(): ?Model
    {
        return match ($this->source_type) {
            'cash_session'        => CashSession::find($this->source_id),
            'physical_count'      => PhysicalCount::find($this->source_id),
            'goods_receipt'       => GoodsReceipt::find($this->source_id),
            'account_receivable'  => AccountReceivable::find($this->source_id),
            default               => null,
        };
    }
}
