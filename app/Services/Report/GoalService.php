<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Exceptions\GoalConflictException;
use App\Models\BusinessGoal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

final class GoalService
{
    /** @param array{kpi_code:string, period_type:string, period_start:string, period_end:string, target_value:string, branch_id?:int|null} $data */
    public function crear(array $data): BusinessGoal
    {
        $this->assertUnica($data['kpi_code'], $data['period_type'], $data['period_start'], $data['branch_id'] ?? null);

        try {
            $goal = new BusinessGoal();
            $goal->branch_id    = $data['branch_id'] ?? null;
            $goal->kpi_code     = $data['kpi_code'];
            $goal->period_type  = $data['period_type'];
            $goal->period_start = $data['period_start'];
            $goal->period_end   = $data['period_end'];
            $goal->target_value = (string) $data['target_value'];
            $goal->created_by   = Auth::id(); // No-repudio: ROL-01 que fijó la meta.
            $goal->save();                     // business_id vía trait; branch_key generada por el motor.

            return $goal->refresh();
        } catch (QueryException $e) {
            // Colisión concurrente: el candado uniq_business_goal (branch_key colapsa el NULL) es la
            // barrera real; se traduce el 1062 a un 422 controlado, nunca un 500 crudo.
            if ($this->isDuplicate($e)) {
                throw new GoalConflictException();
            }

            throw $e;
        }
    }

    /**
     * Actualiza la meta VIVA (target_value y/o fin de período). La identidad
     * (negocio, sucursal, KPI, tipo, inicio) es inmutable: no rompe la unicidad histórica ni
     * altera los snapshots ya congelados (que guardan su propio target al calcularse).
     *
     * @param array{target_value?:string, period_end?:string} $data
     */
    public function actualizar(BusinessGoal $goal, array $data): BusinessGoal
    {
        if (array_key_exists('target_value', $data)) {
            $goal->target_value = (string) $data['target_value'];
        }
        if (array_key_exists('period_end', $data)) {
            $goal->period_end = $data['period_end'];
        }

        $goal->save(); // chk_goal_period y chk_goal_target_positive respaldan la coherencia.

        return $goal->refresh();
    }

    public function eliminar(BusinessGoal $goal): void
    {
        $goal->delete();
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

    private function isDuplicate(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
