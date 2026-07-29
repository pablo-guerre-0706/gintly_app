<?php

declare(strict_types=1);

namespace App\Services\Cash;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\CashAuthorizationException;
use App\Exceptions\CashSessionConflictException;
use App\Exceptions\InvalidRefundMethodException;
use App\Exceptions\NoActiveCashSessionException;
use App\Exceptions\UnreconciledCashClosingException;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


final class CashService
{
    private const MONEY_SCALE = 2;

    /**
     * Apertura con fondo inicial. Los candados de motor (open_register_lock / open_user_lock)
     * impiden la doble apertura; se captura la violación de unicidad y se traduce a 409 legible.
     */
    public function abrir(User $actor, int $cashRegisterId, string $openingAmount): CashSession
    {
        try {
            return DB::transaction(function () use ($actor, $cashRegisterId, $openingAmount): CashSession {
                $session = new CashSession([
                    'cash_register_id' => $cashRegisterId,
                    'opening_amount'   => $openingAmount,
                    'opened_at'        => Carbon::now(),
                ]);
                // opened_by y status fuera de fillable: asignación directa.
                $session->opened_by = $actor->id;
                $session->status = CashSessionStatus::Abierta;
                $session->save();

                return $session->refresh();
            });
        } catch (QueryException $e) {
            // 1062 = violación de índice único. Distinguimos cuál candado saltó.
            if ($this->isUniqueViolation($e)) {
                $message = $e->getMessage();

                if (str_contains($message, 'uniq_open_session_per_user')) {
                    throw CashSessionConflictException::userBusy();
                }

                throw CashSessionConflictException::registerBusy();
            }

            throw $e;
        }
    }

    /**
     * Movimiento manual. Exige sesión ABIERTA (verificada bajo lock).
     * Valida que el autorizante de un egreso autorizado sea ROL-02.
     */
    public function registrarMovimiento(
        User $actor,
        int $cashSessionId,
        CashMovementType $type,
        CashMovementCategory $category,
        PaymentMethod $paymentMethod,
        string $amount,
        ?int $authorizedBy,
        ?string $description
    ): CashMovement {
        return DB::transaction(function () use (
            $actor, $cashSessionId, $type, $category, $paymentMethod, $amount, $authorizedBy, $description
        ): CashMovement {
            $session = $this->lockOpenSession($actor->business_id, $cashSessionId);

            // El autorizante de un egreso autorizado debe ser ROL-02.
            if ($category->requiresAuthorization()) {
                $this->assertAuthorizerIsAdmin($actor->business_id, $authorizedBy);
            }

            $movement = new CashMovement([
                'cash_session_id' => $session->id,
                'type'            => $type,
                'category'        => $category,
                'payment_method'  => $paymentMethod,
                'amount'          => $amount,
                'authorized_by'   => $authorizedBy,
                'description'     => $description,
            ]);
            $movement->user_id = $actor->id;
            $movement->save();

            return $movement->refresh();
        });
    }

    /**
     * Cierre con arqueo ciego. Calcula el esperado tras recibir el conteo, persiste todo,
     * y si hay descuadre lo deja como evidencia y lanza 422 DESPUÉS del commit.
     *
     * @param  array<int, array{value: string, qty: int}>  $denominations
     */
    public function cerrar(
        User $actor,
        CashSession $session,
        string $countedAmount,
        array $denominations,
        ?string $closingNotes
    ): CashSession {
        $closed = DB::transaction(function () use ($actor, $session, $countedAmount, $denominations, $closingNotes): CashSession {
            $session = CashSession::query()
                ->where('business_id', $actor->business_id)
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $session->status->isOpen()) {
                throw NoActiveCashSessionException::forClosing($session->id);
            }

            // Esperado = fondo inicial + Σ(ingresos efectivo)
            //                              − Σ(egresos efectivo).
            $expected = $this->computeExpectedCash($session);

            $session->counted_amount = $countedAmount;
            $session->counted_denominations = $denominations;
            $session->expected_amount = $expected;
            $session->closed_by = $actor->id;
            $session->closed_at = Carbon::now();
            $session->closing_notes = $closingNotes;

            // difference es columna generada (counted − expected): el motor la
            // calcula al guardar. Se decide el estado comparando con bcmath.
            $difference = bcsub($countedAmount, $expected, self::MONEY_SCALE);

            if (bccomp($difference, '0', self::MONEY_SCALE) === 0) {
                $session->status = CashSessionStatus::Cerrada;
            } else {
                // D-20 · Descuadre: se persiste como evidencia, no se revierte.
                $session->status = CashSessionStatus::Descuadrada;
            }

            $session->save();

            // Hook de anomalía (D-24): inerte hasta MOD-11.
            if ($session->status === CashSessionStatus::Descuadrada) {
                $this->dispatchDescuadreAnomaly($session, $difference);
            }

            return $session->refresh();
        });

        // La señal 422 se emite DESPUÉS del commit. La evidencia persiste.
        if ($closed->status === CashSessionStatus::Descuadrada) {
            throw new UnreconciledCashClosingException($closed);
        }

        return $closed;
    }

    /**
     * H-65 / P3 · Registra el movimiento de caja 'venta' generado por el cobro
     * en efectivo de una factura. Reutiliza registrarMovimiento con la categoría
     * y tipo forzados, y adjunta el sale_id (P3). Exige sesión abierta bajo lock.
     */
    public function registrarMovimientoVenta(User $actor, int $cashSessionId, string $amount, int $saleId): \App\Models\CashMovement
    {
        return DB::transaction(function () use ($actor, $cashSessionId, $amount, $saleId): \App\Models\CashMovement {
            $session = $this->lockOpenSession($actor->business_id, $cashSessionId);

            $movement = new \App\Models\CashMovement([
                'cash_session_id' => $session->id,
                'type'            => CashMovementType::Ingreso,
                'category'        => CashMovementCategory::Venta,
                'payment_method'  => PaymentMethod::Efectivo,
                'amount'          => $amount,
                'sale_id'         => $saleId,
                'description'     => 'Venta facturada',
            ]);
            $movement->user_id = $actor->id;
            $movement->save();

            return $movement->refresh();
        });
    }

    /**
     * RF-10-03 · Reembolso en efectivo de una devolución.
     * Bloquea la sesión, exige que esté ABIERTA y asienta un egreso 'egreso_autorizado'
     * (efectivo) autorizado por ROL-01. Debe invocarse dentro de la transacción del retorno.
     */
    public function registrarReembolsoDevolucion(int $cashSessionId, string $amount, int $authorizerId, string $reference): CashMovement
    {
        $session = CashSession::query()->whereKey($cashSessionId)->lockForUpdate()->first();

        if ($session === null || $session->status->value !== 'abierta') {
            throw InvalidRefundMethodException::noActiveCashSession(); // ERR-10B (422).
        }

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'user_id'         => Auth::id(),          // Quien ejecuta el reembolso.
            'type'            => 'egreso',
            'category'        => 'egreso_autorizado',
            'payment_method'  => 'efectivo',
            'amount'          => $amount,
            'sale_id'         => null,
            'authorized_by'   => $authorizerId,       // ROL-01 (chk_cash_movement_egreso_auth).
            'description'     => "Reembolso de devolución · Ref: {$reference}",
        ]);
    }

    /**
     * Bloqueo pesimista de la sesión abierta. Es la barrera anti-carrera de
     * RF-06-02: un movimiento no puede colarse mientras otro proceso cierra.
     */
    private function lockOpenSession(int $businessId, int $cashSessionId): CashSession
    {
        $session = CashSession::query()
            ->where('business_id', $businessId)
            ->whereKey($cashSessionId)
            ->lockForUpdate()
            ->first();

        if ($session === null || ! $session->status->isOpen()) {
            throw NoActiveCashSessionException::forMovement($cashSessionId);
        }

        return $session;
    }

    /**
     * Suma de efectivo esperado. Solo movimientos payment_method=efectivo
     * y categorías que cuentan (excluye fondo_inicial, ya en opening_amount).
     */
    private function computeExpectedCash(CashSession $session): string
    {
        $expected = (string) $session->opening_amount;

        $cashMovements = $session->movements()
            ->where('payment_method', PaymentMethod::Efectivo->value)
            ->get(['type', 'category', 'amount']);

        foreach ($cashMovements as $movement) {
            /** @var CashMovementCategory $category */
            $category = $movement->category;

            if (! $category->countsInExpected()) {
                continue; // fondo_inicial: ya contabilizado en opening_amount.
            }

            /** @var CashMovementType $type */
            $type = $movement->type;
            $signed = bcmul((string) $movement->amount, (string) $type->signedFactor(), self::MONEY_SCALE);
            $expected = bcadd($expected, $signed, self::MONEY_SCALE);
        }

        return $expected;
    }

    // El autorizante existe, es del tenant y tiene rango ROL-02+.
    private function assertAuthorizerIsAdmin(int $businessId, ?int $authorizedBy): void
    {
        if ($authorizedBy === null) {
            throw CashAuthorizationException::notAdmin(0);
        }

        $authorizer = User::query()
            ->where('business_id', $businessId)
            ->whereKey($authorizedBy)
            ->first();

        if ($authorizer === null) {
            throw CashAuthorizationException::notAdmin($authorizedBy);
        }

        // El equipo de permisos ya está fijado por el middleware SetPermissionsTeamId.
        $roleName = $authorizer->getRoleNames()->first();
        $role = $roleName !== null ? RoleName::tryFrom((string) $roleName) : null;

        if ($role === null || ! $role->atLeast(RoleName::Admin)) {
            throw CashAuthorizationException::notAdmin($authorizedBy);
        }
    }

    /**
    * Paso 5 del abono (RF-08-03): movimiento de caja del cobro en efectivo.
    * Bloquea la sesión, exige que esté ABIERTA y asienta un ingreso categoría 'cobro_credito'.
    * DEBE invocarse DENTRO de la transacción del abono (ReceivableService::abonar) para que,
    * si la caja está cerrada, todo el abono se revierta (atomicidad de 5 pasos).
    */
    public function registrarCobroCredito(int $cashSessionId, string $amount, ?string $reference = null): CashMovement
    {
        // Global scope de BelongsToBusiness ⇒ la sesión debe pertenecer al tenant vigente.
        $session = CashSession::query()
            ->whereKey($cashSessionId)
            ->lockForUpdate()
            ->first();

        if ($session === null || $session->status->value !== 'abierta') {
            throw NoActiveCashSessionException::forCreditPayment(); // 409 (ERR-08B).
        }

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'user_id'         => Auth::id(),   // Responsable = quien cobra (no-repudio).
            'type'            => 'ingreso',    // El cast a CashMovementType convierte el string.
            'category'        => 'cobro_credito',
            'payment_method'  => 'efectivo',   // Solo efectivo genera movimiento de caja.
            'amount'          => $amount,
            'sale_id'         => null,         // No proviene de una venta; es cobro de CxC.
            'authorized_by'   => null,         // Un ingreso no requiere autorizante.
            'description'     => $reference !== null
                ? "Cobro de crédito · Ref: {$reference}"
                : 'Cobro de crédito',
        ]);
    }

    /**
     * D-24 · Hook de anomalía de descuadre. INERTE hasta MOD-11: mientras el
     * AnomalyService no exista, no hay a quién despachar. Al cerrar MOD-11 se
     * retira el class_exists y se inyecta el servicio real.
     */
    private function dispatchDescuadreAnomaly(CashSession $session, string $difference): void
    {
        if (! class_exists(\App\Services\Anomaly\AnomalyService::class)) {
            return;
        }

        // El cableado real se completa en MOD-11 (registro de la anomalía
        // 'descuadre_caja' con origen = esta sesión y el monto de la diferencia).
    }

    /**
     * Detecta violación de índice único (SQLSTATE 23000 / errno 1062).
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
