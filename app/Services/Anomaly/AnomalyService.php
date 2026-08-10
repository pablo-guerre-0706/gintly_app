<?php

declare(strict_types=1);

namespace App\Services\Anomaly;

use App\Enums\AnomalyStatus;
use App\Exceptions\InvalidAnomalyStateException;
use App\Exceptions\SelfResolutionNotAllowedException;
use App\Models\Anomaly;
use App\Models\AnomalyEvent;
use App\Models\AnomalyRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class AnomalyService
{
    /**
     * Mapa causante por tabla origen. Ausente ⇒ sin causante humano (no aplica).
     * @var array<string, string>
     */
    private const CAUSER_FIELDS = [
        'cash_sessions'   => 'user_id',
        'goods_receipts'  => 'user_id',
        'physical_counts' => 'user_id',
    ];

    /**
     * RF-11-01/08 · Registro SILENCIOSO e idempotente de una anomalía.
     * Nunca rompe la operación anfitriona: captura el 1062 del candado de idempotencia
     * (ya existe una activa por regla+origen ⇒ ERR-11B silencioso) y cualquier otro error.
     *
     * @param array{expected_value?:string|null, actual_value?:string|null, difference?:string|null, branch_id?:int|null, severity?:\App\Enums\AnomalySeverity|null, reconciliation_run_id?:int|null} $values
     */
    public function registrarSilencioso(string $ruleCode, Model $source, array $values = []): ?Anomaly
    {
        try {
            return DB::transaction(function () use ($ruleCode, $source, $values): ?Anomaly {
                $rule = AnomalyRule::query()
                    ->where('code', $ruleCode)
                    ->where('is_active', true)
                    ->first();

                if ($rule === null) {
                    return null; // Regla inexistente o desactivada: no se detecta.
                }

                $difference = $values['difference'] ?? null;
                if (! $this->superaUmbral($rule, $difference)) {
                    return null; // Bajo umbral: no se genera anomalía.
                }

                $anomaly = new Anomaly();
                $anomaly->anomaly_rule_id       = $rule->id;
                $anomaly->reconciliation_run_id = $values['reconciliation_run_id'] ?? null;
                $anomaly->branch_id             = $values['branch_id'] ?? ($source->branch_id ?? null);
                $anomaly->severity              = $values['severity'] ?? $rule->default_severity;
                $anomaly->status                = AnomalyStatus::Detectada;
                $anomaly->expected_value        = $values['expected_value'] ?? null;
                $anomaly->actual_value          = $values['actual_value'] ?? null;
                $anomaly->difference            = $difference;
                $anomaly->source_type           = $source->getTable(); // Puntero débil = nombre de tabla.
                $anomaly->source_id             = $source->getKey();
                $anomaly->detected_at           = now();
                $anomaly->save(); // uniq_active_anomaly puede lanzar 1062 ⇒ idempotencia.

                $this->writeEvent($anomaly, null, AnomalyStatus::Detectada, null);

                return $anomaly;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateActive($e)) {
                return null; // Ya existe una activa por regla+origen (ERR-11B silencioso).
            }
            report($e); // Otro error de BD: nunca romper la operación host.
            return null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * RF-11-07 · Justifica una anomalía (ROL-02). BR-01: el causante no puede justificarla.
     */
    public function justificar(Anomaly $anomaly, string $reason): Anomaly
    {
        return DB::transaction(function () use ($anomaly, $reason): Anomaly {
            $anomaly = Anomaly::query()->whereKey($anomaly->getKey())->lockForUpdate()->firstOrFail();

            if (! $anomaly->canJustify()) {
                throw InvalidAnomalyStateException::notJustifiable();
            }

            $this->assertNotCauser($anomaly); // BR-01 (ERR-11, 403).

            $from = $anomaly->status;
            $anomaly->status      = AnomalyStatus::Justificada; // active_dedupe_key → NULL (libera candado).
            $anomaly->resolved_by = Auth::id();
            $anomaly->resolved_at = now();
            $anomaly->save();

            $this->writeEvent($anomaly, $from, AnomalyStatus::Justificada, $reason);

            return $anomaly;
        });
    }

    /**
     * Marca la anomalía como resuelta (ROL-01). Libera el candado de idempotencia.
     */
    public function resolver(Anomaly $anomaly, ?string $comment = null): Anomaly
    {
        return DB::transaction(function () use ($anomaly, $comment): Anomaly {
            $anomaly = Anomaly::query()->whereKey($anomaly->getKey())->lockForUpdate()->firstOrFail();

            if (! $anomaly->canResolve()) {
                throw InvalidAnomalyStateException::notResolvable();
            }

            $from = $anomaly->status;
            $anomaly->status      = AnomalyStatus::Resuelta;
            $anomaly->resolved_by = Auth::id();
            $anomaly->resolved_at = now();
            $anomaly->save();

            $this->writeEvent($anomaly, $from, AnomalyStatus::Resuelta, $comment);

            return $anomaly;
        });
    }

    // ---------------- Helpers ----------------

    /** BR-01: rechaza si quien valida es el causante extraído del origen. */
    private function assertNotCauser(Anomaly $anomaly): void
    {
        $causerField = self::CAUSER_FIELDS[$anomaly->source_type] ?? null;

        if ($causerField === null || $anomaly->source_id === null) {
            return; // Sin causante humano trazable: BR-01 no aplica.
        }

        $causerId = DB::table($anomaly->source_type)
            ->where('id', $anomaly->source_id)
            ->where('business_id', $anomaly->business_id)
            ->value($causerField);

        if ($causerId !== null && (int) $causerId === (int) Auth::id()) {
            throw new SelfResolutionNotAllowedException();
        }
    }

    private function superaUmbral(AnomalyRule $rule, ?string $difference): bool
    {
        if ($rule->threshold_value === null || $difference === null) {
            return true; // Sin umbral o sin métrica: siempre se genera.
        }

        // Fase 1: monto/cantidad ⇒ |diferencia| >= umbral. Porcentaje/tiempo: el caller precalcula 'difference'.
        return bccomp($this->abs((string) $difference), (string) $rule->threshold_value, 2) >= 0;
    }

    private function writeEvent(Anomaly $anomaly, ?AnomalyStatus $from, AnomalyStatus $to, ?string $comment): void
    {
        AnomalyEvent::create([
            'anomaly_id'  => $anomaly->id,
            'user_id'     => Auth::id(),        // NULL en procesos programados.
            'from_status' => $from?->value,
            'to_status'   => $to->value,
            'comment'     => $comment,
            'changed_at'  => now(),
        ]);
    }

    private function isDuplicateActive(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            && str_contains((string) $e->getMessage(), 'uniq_active_anomaly');
    }

    private function abs(string $value): string
    {
        return bccomp($value, '0', 2) < 0 ? bcmul($value, '-1', 2) : $value;
    }
}
