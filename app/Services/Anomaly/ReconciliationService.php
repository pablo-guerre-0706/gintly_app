<?php

declare(strict_types=1);

namespace App\Services\Anomaly;

use App\Enums\ReconciliationScope;
use App\Enums\ReconciliationStatus;
use App\Models\AccountReceivable;
use App\Models\CashSession;
use App\Models\GoodsReceipt;
use App\Models\PhysicalCount;
use App\Models\ReconciliationRun;
use Illuminate\Support\Facades\Auth;

final class ReconciliationService
{
    public function __construct(private readonly AnomalyService $anomalies)
    {
    }

    /**
     * RF-11-04 · Ejecuta una conciliación (manual o programada) por alcance.
     * @param 'programada'|'manual' $runType
     */
    public function conciliar(int $businessId, ReconciliationScope $scope, ?int $branchId, string $runType, ?int $triggeredBy): ReconciliationRun
    {
        $run = new ReconciliationRun();
        $run->business_id     = $businessId; // Explícito: el cron corre sin tenant resuelto.
        $run->branch_id       = $branchId;
        $run->triggered_by    = $triggeredBy;
        $run->scope           = $scope;
        $run->run_type        = \App\Enums\ReconciliationRunType::from($runType);
        $run->status          = ReconciliationStatus::EnProceso;
        $run->anomalies_found = 0;
        $run->started_at      = now();
        $run->save();

        try {
            $found = match ($scope) {
                ReconciliationScope::Caja             => $this->reconciliarCaja($businessId, $branchId, $run->id),
                ReconciliationScope::InventarioBodega => $this->reconciliarInventario($businessId, $branchId, $run->id),
                ReconciliationScope::Compras3Way      => $this->reconciliar3Way($businessId, $branchId, $run->id),
                ReconciliationScope::Integral         =>
                    $this->reconciliarCaja($businessId, $branchId, $run->id)
                    + $this->reconciliarInventario($businessId, $branchId, $run->id)
                    + $this->reconciliar3Way($businessId, $branchId, $run->id),
            };

            $run->anomalies_found = $found;
            $run->status          = ReconciliationStatus::Completada;
        } catch (\Throwable $e) {
            report($e);
            $run->status = ReconciliationStatus::Fallida;
        }

        $run->finished_at = now();
        $run->save();

        return $run->fresh(['anomalies']);
    }

    // ---------------- Detectores (solo lectura) ----------------

    /** Descuadre de caja: sesiones cerradas con diferencia distinta de cero (RF-06). */
    private function reconciliarCaja(int $businessId, ?int $branchId, int $runId): int
    {
        $sessions = CashSession::query()
            ->withoutGlobalScopes()->where('business_id', $businessId)
            ->where('status', 'cerrada')
            ->whereNotNull('difference')
            ->where('difference', '<>', 0)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            $registered = $this->anomalies->registrarSilencioso('descuadre_caja', $session, [
                'expected_value'        => (string) ($session->expected_amount ?? '0.00'),
                'actual_value'          => (string) ($session->counted_amount ?? '0.00'),
                'difference'            => (string) $session->difference,
                'branch_id'             => $branchId,
                'reconciliation_run_id' => $runId,
            ]);
            if ($registered !== null) {
                $count++;
            }
        }

        return $count;
    }

    /** Faltante de inventario: conteos físicos recientes con diferencia negativa (RF-03). */
    private function reconciliarInventario(int $businessId, ?int $branchId, int $runId): int
    {
        $counts = PhysicalCount::query()
            ->withoutGlobalScopes()->where('business_id', $businessId)
            ->where('difference', '<', 0)
            ->where('created_at', '>=', now()->subDays(7)) // Ventana: evita re-detectar históricos ya cerrados.
            ->get();

        $count = 0;
        foreach ($counts as $physicalCount) {
            $registered = $this->anomalies->registrarSilencioso('faltante_inventario', $physicalCount, [
                'difference'            => (string) $this->abs((string) $physicalCount->difference),
                'branch_id'             => $branchId,
                'reconciliation_run_id' => $runId,
            ]);
            if ($registered !== null) {
                $count++;
            }
        }

        return $count;
    }

    /** Discrepancia 3-way: recepciones de compra marcadas con discrepancia (RF-04). */
    private function reconciliar3Way(int $businessId, ?int $branchId, int $runId): int
    {
        $receipts = GoodsReceipt::query()
            ->withoutGlobalScopes()->where('business_id', $businessId)
            ->where('has_discrepancy', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $count = 0;
        foreach ($receipts as $receipt) {
            $registered = $this->anomalies->registrarSilencioso('discrepancia_3way', $receipt, [
                'branch_id'             => $branchId,
                'reconciliation_run_id' => $runId,
            ]);
            if ($registered !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function abs(string $value): string
    {
        return bccomp($value, '0', 2) < 0 ? bcmul($value, '-1', 2) : $value;
    }
}
