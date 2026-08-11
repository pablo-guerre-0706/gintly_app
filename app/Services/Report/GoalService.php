<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Exceptions\GoalConflictException;
use App\Models\BusinessGoal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class GoalService
{
    /** @param array{kpi_code:string, period_type:string, period_start:string, period_end:string, target_value:string, branch_id?:int|null} $data */
    public function crear(array $data): BusinessGoal
    {
        $this->assertUnica($data['kpi_code'], $data['period_type'], $data['period_start'], $data['branch_id'] ?? null);

        $goal = new BusinessGoal();
        $goal->branch_id    = $data['branch_id'] ?? null;
        $goal->kpi_code     = $data['kpi_code'];
        $goal->period_type  = $data['period_type'];
        $goal->period_start = $data['period_start'];
        $goal->period_end   = $data['period_end'];
        $goal->target_value = (string) $data['target_value'];
        $goal->created_by   = Auth::id(); // No-repudio: ROL-01 que fijó la meta.
        $goal->save();                     // business_id vía trait.

        return $goal;
    }

    private function assertUnica(string $kpiCode, string $periodType, string $periodStart, ?int $branchId): void
    {
        $exists = BusinessGoal::query()
            ->where('kpi_code', $kpiCode)
            ->where('period_type', $periodType)
            ->where('period_start', $periodStart)
            ->when($branchId === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $branchId))
            ->exists();

        if ($exists) {
            throw new GoalConflictException(); // 422.
        }
    }
}
