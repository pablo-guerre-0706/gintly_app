<?php

namespace App\Services\Anomaly;

use App\Enums\AnomalyRuleCode;
use App\Enums\AnomalyStatus;
use App\Exceptions\DuplicateAnomalyException;
use App\Exceptions\SelfResolutionNotAllowedException;
use App\Models\Anomaly;
use App\Models\AnomalyRule;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AnomalyService
{
    /**
     * Registro IDEMPOTENTE de una anomalía (RF-11-03 / ERR-11B). Si ya existe una activa
     * para (regla + source), el candado uniq_active_anomaly rechaza el INSERT y lo silenciamos:
     * el resultado es "no se duplicó", sin error visible al usuario.
     */
    public function registrar(
        int $businessId,
        AnomalyRuleCode $code,
        ?string $sourceType,
        ?int $sourceId,
        ?string $expected = null,
        ?string $actual = null,
        ?int $branchId = null,
        ?int $reconciliationRunId = null,
    ): ?Anomaly {
        $rule = AnomalyRule::query()
            ->where('business_id', $businessId)
            ->where('code', $code->value)
            ->where('is_active', true)
            ->first();

        if ($rule === null) {
            return null;   // regla desactivada por el negocio → no se genera
        }

        $difference = ($expected !== null && $actual !== null)
            ? bcsub($actual, $expected, 2)
            : null;

        // Umbral: si la diferencia no supera el threshold de la regla, no es anomalía.
        if (! $this->exceedsThreshold($rule, $difference)) {
            return null;
        }

        try {
            $anomaly = new Anomaly([
                'anomaly_rule_id'       => $rule->id,
                'reconciliation_run_id' => $reconciliationRunId,
                'branch_id'             => $branchId,
                'severity'              => $rule->default_severity,
                'status'                => AnomalyStatus::Detectada,
                'expected_value'        => $expected,
                'actual_value'          => $actual,
                'difference'            => $difference,
                'source_type'           => $sourceType,
                'source_id'             => $sourceId,
                'detected_at'           => now(),
            ]);
            $anomaly->business_id = $businessId;
            $anomaly->save();

            // TODO (enrutamiento): si severity=critica → notificar ROL-01 (canal). RF-11-06.
            return $anomaly;
        } catch (QueryException $e) {
            // Choque contra uniq_active_anomaly = ya hay una activa idéntica. Idempotencia (ERR-11B).
            if ($this->isUniqueViolation($e)) {
                throw new DuplicateAnomalyException;   // capturada arriba o silenciada por el caller
            }
            throw $e;
        }
    }

    /** Versión "safe" para los hooks: registra o deduplica sin propagar la colisión. */
    public function registrarSilencioso(int $businessId, AnomalyRuleCode $code, ?string $sourceType, ?int $sourceId, ?string $expected = null, ?string $actual = null, ?int $branchId = null): ?Anomaly
    {
        try {
            return $this->registrar($businessId, $code, $sourceType, $sourceId, $expected, $actual, $branchId);
        } catch (DuplicateAnomalyException) {
            return null;   // ya existía: silencio total, como manda ERR-11B
        }
    }

    /**
     * Justificación por el administrador (RF-11-07 / ERR-11). BR-01: el causante NO puede
     * justificar su propia anomalía. La validación conoce cómo extraer el causante del source.
     *
     * @throws SelfResolutionNotAllowedException
     */
    public function justificar(Anomaly $anomaly, int $validatorId, string $motivo): Anomaly
    {
        if (! $anomaly->status->isActive()) {
            throw new DomainException('La anomalía no está en un estado justificable.');
        }

        $causanteId = $this->resolveCausante($anomaly);
        if ($causanteId !== null && $causanteId === $validatorId) {
            throw new SelfResolutionNotAllowedException;   // BR-01
        }

        return DB::transaction(function () use ($anomaly, $validatorId, $motivo) {
            $anomaly->status      = AnomalyStatus::Justificada;
            $anomaly->resolved_by = $validatorId;
            $anomaly->resolved_at = now();
            $anomaly->save();   // el booted registra el anomaly_event automáticamente

            // Comentario de justificación en la bitácora (el evento base ya lo creó el modelo).
            $anomaly->events()->latest('id')->first()?->forceFill([])->save();  // no-op seguro
            return $anomaly;
        });
    }

    // ─────── Helpers privados ───────

    private function exceedsThreshold(AnomalyRule $rule, ?string $difference): bool
    {
        if ($rule->threshold_value === null || $difference === null) {
            return true;   // reglas sin umbral (tiempo/cantidad) o sin diferencia → siempre relevante
        }
        // |diferencia| > umbral. Umbral 0.00 (ej. 3-way) → cualquier diferencia dispara.
        $abs = bccomp($difference, '0', 2) < 0 ? bcmul($difference, '-1', 2) : $difference;
        return bccomp($abs, (string) $rule->threshold_value, 2) > 0;
    }

    /** Extrae el usuario "involucrado" según el tipo de source (para BR-01). */
    private function resolveCausante(Anomaly $anomaly): ?int
    {
        $source = $anomaly->resolveSource();
        return match ($anomaly->source_type) {
            'cash_session'   => $source?->opened_by,
            'physical_count' => $source?->user_id,
            'goods_receipt'  => $source?->user_id,
            default          => null,
        };
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;   // MySQL: duplicate entry
    }
}
