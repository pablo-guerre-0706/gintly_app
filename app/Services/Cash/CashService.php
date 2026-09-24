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
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use App\Services\Anomaly\AnomalyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CashService
{
    private const MONEY_SCALE = 2;

    /**
     * Constructor para inyección de dependencias automáticas.
     */
    public function __construct(
        private readonly AnomalyService $anomalies,
    ) {}

    /**
     * Apertura con fondo inicial. Los candados de motor (open_register_lock / open_user_lock)
     * impiden la doble apertura; se captura la violación de unicidad y se traduce a 409 legible.
     */
    public function abrir(User $actor, int $cashRegisterId, string $openingAmount): CashSession
    {
        // Aislamiento de sucursal (ROL-03): la caja debe pertenecer a SU sucursal.
        // El FormRequest ya garantizó existencia+activa+tenant; aquí se acota la
        // sucursal antes de abrir. ROL-01/ROL-02 administran cualquier caja del negocio.
        $this->assertCanOpenRegister($actor, $cashRegisterId);

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

            // Propiedad (ROL-03): solo puede registrar movimientos en SU sesión abierta.
            // Se valida bajo lock y antes de persistir, de modo que un rechazo no asienta
            // nada (rollback de la transacción). ROL-01/ROL-02 operan administrativamente.
            $this->assertCanOperateSession($actor, $session);

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

            // Backstop del cierre administrativo por contingencia: si quien cierra no es
            // quien abrió la sesión, el motivo (closing_notes) es OBLIGATORIO y con
            // contenido significativo. En HTTP ya lo exige CloseCashSessionRequest; este
            // guarda cierra la vía no-HTTP. Se lanza ANTES de persistir → nada se modifica.
            $this->assertContingencyNote($actor, $session, $closingNotes);

            // Esperado = fondo inicial + Σ(ingresos efectivo)
            //                              − Σ(egresos efectivo).
            $expected = $this->computeExpectedCash($session);

            $session->counted_amount = $countedAmount;
            $session->counted_denominations = $denominations;
            $session->expected_amount = $expected;
            // closed_by SIEMPRE lo deriva el servidor del actor autenticado; jamás del request.
            $session->closed_by = $actor->id;
            $session->closed_at = Carbon::now();
            $session->closing_notes = $closingNotes;

            // difference es columna generada (counted − expected): el motor la
            // calcula al guardar. Se decide el estado comparando con bcmath.
            $difference = bcsub($countedAmount, $expected, self::MONEY_SCALE);

            if (bccomp($difference, '0', self::MONEY_SCALE) === 0) {
                $session->status = CashSessionStatus::Cerrada;
            } else {
                // Descuadre: se persiste como evidencia, no se revierte.
                $session->status = CashSessionStatus::Descuadrada;
            }

            $session->save();

            return $session->refresh();
        });

        // MOD-11 · descuadre de caja → anomalía silenciosa. Se registra DESPUÉS del
        // commit (la evidencia ya persiste) sobre $closed, la instancia recargada del
        // motor: el parámetro $session original quedó obsoleto (la reasignación dentro
        // del closure no se propaga fuera). La sucursal se deriva de la caja, ya que
        // cash_sessions no tiene columna branch_id propia.
        if (bccomp((string) $closed->difference, '0.00', 2) !== 0) {
            $this->anomalies->registrarSilencioso('descuadre_caja', $closed, [
                'expected_value' => (string) $closed->expected_amount,
                'actual_value'   => (string) $closed->counted_amount,
                'difference'     => (string) $closed->difference,
                'branch_id'      => $closed->cashRegister?->branch_id,
            ]);
        }

        // La señal 422 se emite DESPUÉS del commit. La evidencia persiste.
        if ($closed->status === CashSessionStatus::Descuadrada) {
            throw new UnreconciledCashClosingException($closed);
        }

        return $closed;
    }

    /**
     * Registra el movimiento de caja 'venta' generado por el cobro
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
     * Reembolso en efectivo de una devolución.
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

    /**
     * Motivo obligatorio en el cierre administrativo por contingencia (quien cierra
     * ≠ quien abrió). Backstop no-HTTP; se traduce a 422 sobre closing_notes.
     */
    private function assertContingencyNote(User $actor, CashSession $session, ?string $closingNotes): void
    {
        if ((int) $session->opened_by === (int) $actor->id) {
            return; // Cierre ordinario: el motivo es opcional.
        }

        $note = $closingNotes !== null ? trim($closingNotes) : '';

        if (mb_strlen($note) < 3) {
            throw ValidationException::withMessages([
                'closing_notes' => ['El cierre administrativo de una sesión ajena exige registrar el motivo.'],
            ]);
        }
    }

    /**
     * ROL-03 solo opera sobre una sesión que él mismo abrió; ROL-01/ROL-02 conservan
     * la operación administrativa sobre cualquier sesión del negocio.
     */
    private function assertCanOperateSession(User $actor, CashSession $session): void
    {
        if ($actor->holdsAtLeast(RoleName::Admin)) {
            return;
        }

        if ((int) $session->opened_by !== (int) $actor->id) {
            throw new AuthorizationException('No puede operar sobre una sesión de caja de otro usuario.');
        }
    }

    /**
     * Aislamiento de sucursal en la apertura. ROL-01/ROL-02 abren cualquier caja del
     * negocio; ROL-03 solo cajas de SU sucursal, y si no tiene sucursal asignada la
     * apertura se rechaza de forma controlada (403, no un 500).
     */
    private function assertCanOpenRegister(User $actor, int $cashRegisterId): void
    {
        if ($actor->holdsAtLeast(RoleName::Admin)) {
            return;
        }

        if ($actor->branch_id === null) {
            throw new AuthorizationException('No tiene una sucursal asignada para abrir una caja.');
        }

        // Acotado al tenant por BusinessScope; el FormRequest ya validó existencia/activa.
        $register = CashRegister::query()->whereKey($cashRegisterId)->first();

        if ($register === null || (int) $register->branch_id !== (int) $actor->branch_id) {
            throw new AuthorizationException('No puede abrir una caja de otra sucursal.');
        }
    }

    // El autorizante existe, es del tenant, está ACTIVO y tiene rango ROL-02+.
    private function assertAuthorizerIsAdmin(int $businessId, ?int $authorizedBy): void
    {
        if ($authorizedBy === null) {
            throw CashAuthorizationException::notAdmin(0);
        }

        // Debe estar activo: un ROL-02 inactivo no puede autorizar egresos.
        $authorizer = User::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
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
    * Paso 5 del abono: movimiento de caja del cobro en efectivo.
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
     * Detecta violación de índice único (SQLSTATE 23000 / errno 1062).
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
