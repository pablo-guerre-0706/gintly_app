<?php

namespace App\Services\Anomaly;

use App\Enums\AnomalyRuleCode;
use App\Enums\ReconciliationRunType;
use App\Enums\ReconciliationScope;
use App\Enums\ReconciliationStatus;
use App\Models\CashSession;
use App\Models\PhysicalCount;
use App\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconciliationService
{
    public function __construct(private readonly AnomalyService $anomalies)
    {
    }

    /**
     * Corrida de conciliación (RF-11-01/02/04). Registra la corrida, ejecuta el scope,
     * cuenta hallazgos y cierra. Corre por cron (programada) o a demanda (manual).
     */
    public function ejecutar(int $businessId, ReconciliationScope $scope, ReconciliationRunType $type, ?int $branchId = null, ?int $triggeredBy = null): ReconciliationRun
    {
        $run = ReconciliationRun::create([
            'branch_id'       => $branchId,
            'triggered_by'    => $triggeredBy,
            'run_type'        => $type,
            'scope'           => $scope,
            'status'          => ReconciliationStatus::EnProceso,
            'anomalies_found' => 0,
            'started_at'      => now(),
        ]);

        try {
            $found = match ($scope) {
                ReconciliationScope::Caja             => $this->reconcileCaja($businessId, $run),
                ReconciliationScope::InventarioBodega => $this->reconcileInventario($businessId, $run),
                ReconciliationScope::Compras3Way      => 0, // el 3-way se detecta en tiempo real (MOD-04)
                ReconciliationScope::Integral         => $this->reconcileCaja($businessId, $run) + $this->reconcileInventario($businessId, $run),
            };

            $run->anomalies_found = $found;
            $run->status = ReconciliationStatus::Completada;
            $run->finished_at = now();
            $run->save();
        } catch (Throwable $e) {
            $run->status = ReconciliationStatus::Fallida;
            $run->finished_at = now();
            $run->save();
            throw $e;
        }

        return $run;
    }

    /** Descuadres de caja: sesiones descuadradas sin justificar. */
    private function reconcileCaja(int $businessId, ReconciliationRun $run): int
    {
        $found = 0;
        CashSession::query()
            ->where('business_id', $businessId)
            ->where('status', 'descuadrada')
            ->chunkById(200, function ($sessions) use ($businessId, $run, &$found) {
                foreach ($sessions as $s) {
                    $a = $this->anomalies->registrarSilencioso(
                        businessId: $businessId,
                        code: AnomalyRuleCode::DescuadreCaja,
                        sourceType: 'cash_session',
                        sourceId: $s->id,
                        expected: (string) $s->expected_amount,
                        actual: (string) $s->counted_amount,
                        branchId: $run->branch_id,
                    );
                    if ($a !== null) $found++;
                }
            });
        return $found;
    }

    /** Faltantes de inventario: conteos con diferencia negativa sin justificar. */
    private function reconcileInventario(int $businessId, ReconciliationRun $run): int
    {
        $found = 0;
        PhysicalCount::query()
            ->where('business_id', $businessId)
            ->where('status', 'abierto')
            ->where('difference', '<', 0)
            ->chunkById(200, function ($counts) use ($businessId, $run, &$found) {
                foreach ($counts as $c) {
                    $a = $this->anomalies->registrarSilencioso(
                        businessId: $businessId,
                        code: AnomalyRuleCode::FaltanteInventario,
                        sourceType: 'physical_count',
                        sourceId: $c->id,
                        expected: (string) $c->system_quantity,
                        actual: (string) $c->counted_quantity,
                        branchId: $run->branch_id,
                    );
                    if ($a !== null) $found++;
                }
            });
        return $found;
    }
}
