<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\LimitCheckUnavailableException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Branch;
use App\Models\CashSession;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Límites del PLAN, del negocio completo (ROL-01 incluido). Se cuentan recursos REALES y la comprobación +
 * la mutación ocurren DENTRO del mismo lock por negocio, de modo que la concurrencia no supere el cupo. Un
 * intento bloqueado no deja filas parciales ni consume capacidad.
 *
 *  - Sucursales ACTIVAS no eliminadas (incluye reactivaciones).
 *  - Cajas operando SIMULTÁNEAMENTE = CashSession con sesión operativa abierta.
 *  - Límite null ⇒ sin límite comercial (p. ej. cajas en Plan Cadena).
 */
final class PlanLimits
{
    public function __construct(
        private readonly CommercialAccess $access,
        private readonly BillingCatalog $catalog,
    ) {
    }

    /** Ejecuta $create (creación/reactivación de sucursal) bajo lock, respetando el límite del plan. */
    public function guardBranchActivation(int $businessId, Closure $create): mixed
    {
        return $this->guard($businessId, 'branches', function () use ($businessId): int {
            return (int) Branch::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->count();
        }, $create, fn (int $limit) => PlanLimitExceededException::branches($limit));
    }

    /** Ejecuta $open (apertura de sesión de caja) bajo lock, respetando el límite de cajas simultáneas. */
    public function guardCashSessionOpen(int $businessId, Closure $open): mixed
    {
        return $this->guard($businessId, 'cash_sessions', function () use ($businessId): int {
            return (int) CashSession::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereIn('status', ['abierta', 'descuadrada']) // operativas; 'descuadrada' sigue ocupando caja hasta cerrar
                ->count();
        }, $open, fn (int $limit) => PlanLimitExceededException::cashSessions($limit));
    }

    /**
     * @param  Closure():int  $counter
     * @param  Closure():mixed  $mutate
     * @param  Closure(int):PlanLimitExceededException  $exceeded
     */
    private function guard(int $businessId, string $limitKey, Closure $counter, Closure $mutate, Closure $exceeded): mixed
    {
        // El lock se adquiere PRIMERO; plan, pendiente y límite se RESUELVEN DENTRO de la sección crítica (relectura)
        // para ver un descenso pendiente que un cambio concurrente persista bajo el MISMO lock (sin carrera).
        $lock = 'gintly_plan_'.$limitKey.'_'.$businessId;
        if (! $this->acquireLock($lock)) {
            // No se pudo SERIALIZAR la comprobación (lock no adquirido / espera agotada / error de PDO). Eso es
            // indisponibilidad de INFRAESTRUCTURA, no un cupo superado: fail-closed SIN mutar, pero señalado como
            // 503 LIMIT_CHECK_UNAVAILABLE (nunca como PLAN_LIMIT_EXCEEDED, que afirmaría un exceso real).
            throw new LimitCheckUnavailableException();
        }

        try {
            $plan = $this->access->activePlanKey($businessId);
            // Sin plan vigente la compuerta comercial ya corta la ruta; defensivo, no se aplica límite aquí.
            if ($plan === null) {
                return $mutate();
            }

            // Durante un DESCENSO pendiente, las altas/reactivaciones se ACOTAN al plan DESTINO (menor), de modo que
            // el uso SIEMPRE quepa cuando el descenso se aplique: así no hace falta grandfathering ni borrar recursos,
            // y nunca se cobra un plan distinto del aplicado. Los recursos existentes se conservan.
            $plan = $this->effectiveLimitPlan($businessId, $plan);

            $limit = $this->catalog->limit($plan, $limitKey);
            if ($limit === null) {
                return $mutate(); // sin límite comercial
            }

            if ($counter() >= $limit) {
                throw $exceeded($limit);
            }

            return $mutate();
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Plan cuyos límites rigen las ALTAS ahora: si hay un DESCENSO programado de nivel MENOR, se acota al destino
     * (coordina el uso para que quepa hasta la renovación). En otro caso, el plan vigente.
     */
    private function effectiveLimitPlan(int $businessId, string $currentPlan): string
    {
        $sub = $this->access->subscriptionFor($businessId);
        $pending = $sub?->pending_plan_key;

        if ($pending !== null
            && $this->catalog->tierRank((string) $pending) >= 0
            && $this->catalog->tierRank((string) $pending) < $this->catalog->tierRank($currentPlan)) {
            return (string) $pending;
        }

        return $currentPlan;
    }

    private function acquireLock(string $name): bool
    {
        $timeout = min(30, max(1, (int) config('billing.lock_timeout_seconds', 10)));

        try {
            $row = DB::select('SELECT GET_LOCK(?, ?) AS locked', [$name, $timeout], false);

            return isset($row[0]->locked) && (int) $row[0]->locked === 1;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function releaseLock(string $name): void
    {
        try {
            DB::select('SELECT RELEASE_LOCK(?)', [$name], false);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
