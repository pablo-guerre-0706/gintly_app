<?php

namespace App\Models;

use App\Enums\GoalableKpiCode;
use App\Enums\KpiPeriodType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessGoal extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'branch_id', 'kpi_code', 'period_type', 'period_start', 'period_end', 'target_value', 'created_by',
        // 'branch_key' EXCLUIDO: columna generada (COALESCE(branch_id,0)). Nunca se asigna.
    ];

    protected function casts(): array
    {
        return [
            'kpi_code'     => GoalableKpiCode::class,
            'period_type'  => KpiPeriodType::class,
            'period_start' => 'date',
            'period_end'   => 'date',
            'target_value' => 'decimal:2',
        ];
    }

    // business() del trait
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
