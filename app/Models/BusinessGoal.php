<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessGoalKpiCode;
use App\Enums\PeriodType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BusinessGoal extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'branch_id',
        'kpi_code',
        'period_type',
        'period_start',
        'period_end',
        'target_value',
    ];
    // FUERA de fillable: created_by (lo fija el Service), branch_key (generada).

    protected function casts(): array
    {
        return [
            'kpi_code'     => BusinessGoalKpiCode::class,
            'period_type'  => PeriodType::class,
            'period_start' => 'date',
            'period_end'   => 'date',
            'target_value' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo    { return $this->belongsTo(Branch::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
