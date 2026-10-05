<?php

declare(strict_types=1);

namespace App\Services\Cash;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\CashAuthorizationException;
use App\Exceptions\CashSessionConflictException;
use App\Exceptions\ExchangeRateMissingException;
use App\Exceptions\InvalidRefundMethodException;
use App\Exceptions\NoActiveCashSessionException;
use App\Exceptions\UnreconciledCashClosingException;
use App\Models\CashCount;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterAssignment;
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
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * Resuelve la TASA snapshot para una operación. Si el llamador ya congeló una tasa (p. ej. la
     * factura resuelve una sola vez y la propaga al cobro en efectivo), se respeta ese valor; en otro
     * caso se resuelve la vigente al instante. NIO → '1'. Una moneda extranjera sin tasa vigente lanza
     * ExchangeRateMissingException (422): no se inventa ni se asume.
     */
    private function resolveRate(int $businessId, Currency $currency, ?string $explicitRate): string
    {
        if ($explicitRate !== null) {
            return $explicitRate;
        }

        return $this->exchangeRates->rateFor($businessId, $currency);
    }

    /**
     * Apertura con fondo inicial. Los candados de motor (open_register_lock / open_user_lock)
     * impiden la doble apertura; se captura la violación de unicidad y se traduce a 409 legible.
     */
    public function abrir(User $actor, int $cashRegisterId, string $openingAmount, string $openingAmountUsd = '0.00'): CashSession
    {
        // Aislamiento de sucursal (ROL-03): la caja debe pertenecer a SU sucursal.
        // El FormRequest ya garantizó existencia+activa+tenant; aquí se acota la
        // sucursal antes de abrir. ROL-01/ROL-02 administran cualquier caja del negocio.
        $this->assertCanOpenRegister($actor, $cashRegisterId);

        try {
            return DB::transaction(function () use ($actor, $cashRegisterId, $openingAmount, $openingAmountUsd): CashSession {
                // Fondo inicial SEPARADO por moneda (NIO base + USD). El USD arranca en 0 salvo que se
                // declare, de modo que una apertura NIO pura (payload histórico) no cambia su comportamiento.
                $session = new CashSession([
                    'cash_register_id' => $cashRegisterId,
                    'opening_amount' => $openingAmount,
                    'opening_amount_usd' => $openingAmountUsd,
                    'opened_at' => Carbon::now(),
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
        ?string $description,
        Currency $currency = Currency::Nio
    ): CashMovement {
        return DB::transaction(function () use (
            $actor, $cashSessionId, $type, $category, $paymentMethod, $amount, $authorizedBy, $description, $currency
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

            // Tasa snapshot congelada al instante de la operación (NIO → '1'; USD sin tasa vigente → 422).
            $rate = $this->resolveRate((int) $actor->business_id, $currency, null);

            $movement = new CashMovement([
                'cash_session_id' => $session->id,
                'type' => $type,
                'category' => $category,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'authorized_by' => $authorizedBy,
                'description' => $description,
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
        ?string $closingNotes,
        string $countedAmountUsd = '0.00',
        array $denominationsUsd = []
    ): CashSession {
        $closed = DB::transaction(function () use (
            $actor, $session, $countedAmount, $denominations, $closingNotes, $countedAmountUsd, $denominationsUsd
        ): CashSession {
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

            // Esperado POR MONEDA, en importe nativo. Cada leg: fondo inicial + Σ(efectivo) con signo.
            $expectedNio = $this->computeExpectedCash($session, Currency::Nio);
            $expectedUsd = $this->computeExpectedCash($session, Currency::Usd);

            $session->counted_amount = $countedAmount;
            $session->counted_denominations = $denominations;
            $session->expected_amount = $expectedNio;

            $session->counted_amount_usd = $countedAmountUsd;
            $session->counted_denominations_usd = $denominationsUsd;
            $session->expected_amount_usd = $expectedUsd;

            // closed_by SIEMPRE lo deriva el servidor del actor autenticado; jamás del request.
            $session->closed_by = $actor->id;
            $session->closed_at = Carbon::now();
            $session->closing_notes = $closingNotes;

            // difference / difference_usd son columnas generadas (counted − expected): el motor las
            // calcula al guardar. El estado se decide por moneda con bcmath: una diferencia en NIO O en
            // USD marca 'descuadrada', aunque la otra moneda cuadre y aunque el consolidado coincida.
            $diffNio = bcsub($countedAmount, $expectedNio, self::MONEY_SCALE);
            $diffUsd = bcsub($countedAmountUsd, $expectedUsd, self::MONEY_SCALE);

            $isDescuadrada = bccomp($diffNio, '0', self::MONEY_SCALE) !== 0
                || bccomp($diffUsd, '0', self::MONEY_SCALE) !== 0;

            $session->status = $isDescuadrada
                ? CashSessionStatus::Descuadrada
                : CashSessionStatus::Cerrada;

            // Tasa de REFERENCIA del consolidado informativo (snapshot al cierre). Solo si hay dimensión
            // USD en la sesión; si por alguna razón no hay tasa vigente, el consolidado simplemente no se
            // expresa (null), sin bloquear el cierre: la reconciliación autoritativa es por moneda.
            if ($this->sessionHasUsd($session, $expectedUsd, $countedAmountUsd)) {
                try {
                    $session->session_exchange_rate = $this->exchangeRates->rateFor((int) $actor->business_id, Currency::Usd);
                } catch (ExchangeRateMissingException) {
                    $session->session_exchange_rate = null;
                }
            }

            $session->save();

            return $session->refresh();
        });

        // MOD-11 · descuadre de caja → anomalía silenciosa. Se registra DESPUÉS del commit (la evidencia
        // ya persiste) sobre $closed, la instancia recargada del motor. La anomalía se dispara cuando la
        // sesión quedó 'descuadrada' por CUALQUIER moneda — incluso si el consolidado NIO coincidiera —,
        // y su contexto detalla la diferencia por moneda. Se conservan las claves NIO históricas
        // (expected_value/actual_value/difference) para no romper la conciliación de MOD-11.
        if ($closed->status === CashSessionStatus::Descuadrada) {
            $this->anomalies->registrarSilencioso('descuadre_caja', $closed, [
                'expected_value' => (string) $closed->expected_amount,
                'actual_value' => (string) $closed->counted_amount,
                // 'difference' alimenta el umbral de MOD-11 (|diferencia| ≥ umbral). Con doble moneda se
                // usa el leg de MAYOR magnitud expresado en NIO, para que un descuadre solo-USD no quede
                // bajo el umbral por traer la diferencia NIO en cero. NIO puro ⇒ valor idéntico al previo.
                'difference' => self::consolidatedDeviation($closed),
                'difference_nio' => (string) $closed->difference,
                'difference_usd' => (string) $closed->difference_usd,
                'currencies' => $this->descuadreCurrencies($closed),
                'branch_id' => $closed->cashRegister?->branch_id,
            ]);
        }

        // La señal 422 se emite DESPUÉS del commit. La evidencia persiste.
        if ($closed->status === CashSessionStatus::Descuadrada) {
            throw new UnreconciledCashClosingException($closed);
        }

        return $closed;
    }

    /**
     * Magnitud de descuadre (NIO) para el umbral de MOD-11: el leg de MAYOR valor absoluto —NIO o USD
     * convertido con la tasa de referencia del cierre— preservando su signo. Para una sesión NIO pura
     * (sin USD / sin tasa) devuelve exactamente la diferencia NIO, sin cambiar el comportamiento previo.
     */
    public static function consolidatedDeviation(CashSession $session): string
    {
        $nio = (string) ($session->difference ?? '0.00');
        $rate = $session->session_exchange_rate;
        $usdNative = (string) ($session->difference_usd ?? '0.00');
        $usdNio = $rate !== null ? bcmul($usdNative, (string) $rate, self::MONEY_SCALE) : '0.00';

        $absNio = ltrim($nio, '-') ?: '0';
        $absUsd = ltrim($usdNio, '-') ?: '0';

        return bccomp($absNio, $absUsd, self::MONEY_SCALE) >= 0 ? $nio : $usdNio;
    }

    /** ¿La sesión tiene alguna dimensión en USD (fondo, esperado o contado)? */
    private function sessionHasUsd(CashSession $session, string $expectedUsd, string $countedUsd): bool
    {
        return bccomp((string) $session->opening_amount_usd, '0', self::MONEY_SCALE) !== 0
            || bccomp($expectedUsd, '0', self::MONEY_SCALE) !== 0
            || bccomp($countedUsd, '0', self::MONEY_SCALE) !== 0;
    }

    /**
     * Monedas con descuadre al cierre (evidencia para la anomalía). Una moneda figura solo si SU
     * diferencia es distinta de cero: así el contexto distingue un descuadre exclusivo de USD de uno de NIO.
     *
     * @return array<int, string>
     */
    private function descuadreCurrencies(CashSession $closed): array
    {
        $currencies = [];

        if (bccomp((string) $closed->difference, '0', self::MONEY_SCALE) !== 0) {
            $currencies[] = Currency::Nio->value;
        }

        if ($closed->difference_usd !== null
            && bccomp((string) $closed->difference_usd, '0', self::MONEY_SCALE) !== 0
        ) {
            $currencies[] = Currency::Usd->value;
        }

        return $currencies;
    }

    /**
     * Arqueo ciego INDEPENDIENTE durante una sesión ABIERTA (RF-06-04), SIN cerrarla. Bloquea la
     * sesión, exige que esté abierta, calcula el esperado en ese instante (oculto para el cajero hasta
     * aquí) y congela la evidencia (denominaciones, usuario, fecha). Devuelve el arqueo con el esperado
     * y la diferencia ya REVELADOS. Append-only (múltiples arqueos históricos). DECISIÓN DE DOMINIO:
     * un arqueo NO cambia el estado de la sesión y NO genera anomalía; solo el CIERRE formal
     * (cerrar) marca 'descuadrada' y despacha 'descuadre_caja'. Así la anomalía queda ligada a la
     * reconciliación autoritativa y los arqueos intermedios no generan ruido.
     *
     * @param  array<int, array{value: string, qty: int}>  $denominations
     */
    public function registrarArqueo(
        User $actor,
        CashSession $session,
        string $countedAmount,
        array $denominations,
        string $countedAmountUsd = '0.00',
        array $denominationsUsd = []
    ): CashCount {
        return DB::transaction(function () use (
            $actor, $session, $countedAmount, $denominations, $countedAmountUsd, $denominationsUsd
        ): CashCount {
            $session = $this->lockOpenSession($actor->business_id, $session->getKey());

            // Propiedad (ROL-03): solo arquea SU sesión abierta; ROL-01/ROL-02 operan administrativamente.
            $this->assertCanOperateSession($actor, $session);

            // Esperado POR MONEDA calculado al momento del arqueo (mismo criterio que el cierre).
            $expectedNio = $this->computeExpectedCash($session, Currency::Nio);
            $expectedUsd = $this->computeExpectedCash($session, Currency::Usd);

            $count = new CashCount([
                'cash_session_id' => $session->id,
                'counted_amount' => $countedAmount,
                'expected_amount' => $expectedNio,
                'counted_denominations' => $denominations,
                'counted_amount_usd' => $countedAmountUsd,
                'expected_amount_usd' => $expectedUsd,
                'counted_denominations_usd' => $denominationsUsd,
                'counted_at' => Carbon::now(),
            ]);
            $count->user_id = $actor->id;
            $count->save();

            return $count->refresh();
        });
    }

    /**
     * Registra el movimiento de caja 'venta' generado por el cobro
     * en efectivo de una factura. Reutiliza registrarMovimiento con la categoría
     * y tipo forzados, y adjunta el sale_id (P3). Exige sesión abierta bajo lock.
     */
    public function registrarMovimientoVenta(
        User $actor,
        int $cashSessionId,
        string $amount,
        int $saleId,
        Currency $currency = Currency::Nio,
        ?string $exchangeRate = null
    ): CashMovement {
        return DB::transaction(function () use ($actor, $cashSessionId, $amount, $saleId, $currency, $exchangeRate): CashMovement {
            $session = $this->lockOpenSession($actor->business_id, $cashSessionId);

            // Invariante de dominio (no solo del FormRequest): el cobro en efectivo se asienta
            // SOLO en la sesión propia del operador (ROL-03); ROL-01/ROL-02 pueden operar cualquiera.
            // Impide que una llamada interna use la sesión de otro usuario saltándose la validación HTTP.
            $this->assertCanOperateSession($actor, $session);

            // Tasa snapshot: la factura congela una sola tasa por operación y la propaga; si no se
            // suministra, se resuelve la vigente. El importe es NATIVO en la moneda del pago.
            $rate = $this->resolveRate((int) $actor->business_id, $currency, $exchangeRate);

            $movement = new CashMovement([
                'cash_session_id' => $session->id,
                'type' => CashMovementType::Ingreso,
                'category' => CashMovementCategory::Venta,
                'payment_method' => PaymentMethod::Efectivo,
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'sale_id' => $saleId,
                'description' => 'Venta facturada',
            ]);
            $movement->user_id = $actor->id;
            $movement->save();

            return $movement->refresh();
        });
    }

    /**
     * Vuelto (CAMBIO) en efectivo de un cobro con doble moneda. Egreso real del cajón en la moneda del
     * cambio, con su importe nativo y tasa snapshot. DEBE invocarse DENTRO de la transacción de la factura
     * (InvoiceService) para que, si la caja no está abierta o algo falla, todo el cobro se revierta.
     * No requiere autorizante: es el cambio de una venta, no un egreso autorizado.
     */
    public function registrarVuelto(
        User $actor,
        int $cashSessionId,
        string $amount,
        int $saleId,
        Currency $currency = Currency::Nio,
        ?string $exchangeRate = null
    ): CashMovement {
        return DB::transaction(function () use ($actor, $cashSessionId, $amount, $saleId, $currency, $exchangeRate): CashMovement {
            $session = $this->lockOpenSession($actor->business_id, $cashSessionId);
            $this->assertCanOperateSession($actor, $session);

            $rate = $this->resolveRate((int) $actor->business_id, $currency, $exchangeRate);

            $movement = new CashMovement([
                'cash_session_id' => $session->id,
                'type' => CashMovementType::Egreso,
                'category' => CashMovementCategory::Vuelto,
                'payment_method' => PaymentMethod::Efectivo,
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'sale_id' => $saleId,
                'description' => 'Vuelto de venta facturada',
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
    public function registrarReembolsoDevolucion(
        int $cashSessionId,
        string $amount,
        int $authorizerId,
        string $reference,
        Currency $currency = Currency::Nio,
        ?string $exchangeRate = null
    ): CashMovement {
        $session = CashSession::query()->whereKey($cashSessionId)->lockForUpdate()->first();

        if ($session === null || $session->status->value !== 'abierta') {
            throw InvalidRefundMethodException::noActiveCashSession(); // ERR-10B (422).
        }

        $rate = $this->resolveRate((int) $session->business_id, $currency, $exchangeRate);

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'user_id' => Auth::id(),          // Quien ejecuta el reembolso.
            'type' => 'egreso',
            'category' => 'egreso_autorizado',
            'payment_method' => 'efectivo',
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $rate,
            'sale_id' => null,
            'authorized_by' => $authorizerId,       // ROL-01 (chk_cash_movement_egreso_auth).
            'description' => "Reembolso de devolución · Ref: {$reference}",
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
     * Efectivo esperado de UNA moneda, en su importe NATIVO (NIO en NIO, USD en USD). Cada moneda se
     * reconcilia por separado: se parte de su propio fondo inicial y se suman solo los movimientos en
     * efectivo de ESA moneda cuya categoría cuenta (excluye fondo_inicial, ya contabilizado en el fondo).
     * El importe nativo nunca se convierte aquí: la consolidación a NIO es informativa y vive en el Resource.
     */
    private function computeExpectedCash(CashSession $session, Currency $currency): string
    {
        $expected = $currency->isBase()
            ? (string) $session->opening_amount
            : (string) $session->opening_amount_usd;

        $cashMovements = $session->movements()
            ->where('payment_method', PaymentMethod::Efectivo->value)
            ->where('currency', $currency->value)
            ->get(['type', 'category', 'amount']);

        foreach ($cashMovements as $movement) {
            /** @var CashMovementCategory $category */
            $category = $movement->category;

            if (! $category->countsInExpected()) {
                continue; // fondo_inicial: ya contabilizado en el fondo de apertura.
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

        // Asignación Caja–Cajero: un ROL-03 solo abre la caja que tenga ACTIVAMENTE asignada (RF-06).
        // Invariante de dominio en el Service (no solo del FormRequest): sin asignación vigente, no abre.
        $assigned = CashRegisterAssignment::query()
            ->where('business_id', $actor->business_id)
            ->where('cash_register_id', $cashRegisterId)
            ->where('user_id', $actor->id)
            ->whereNull('ended_at')
            ->exists();

        if (! $assigned) {
            throw new AuthorizationException('No tiene asignada esta caja; solicite al administrador la asignación.');
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
    public function registrarCobroCredito(
        int $cashSessionId,
        string $amount,
        ?string $reference = null,
        Currency $currency = Currency::Nio,
        ?string $exchangeRate = null
    ): CashMovement {
        // Global scope de BelongsToBusiness ⇒ la sesión debe pertenecer al tenant vigente.
        $session = CashSession::query()
            ->whereKey($cashSessionId)
            ->lockForUpdate()
            ->first();

        if ($session === null || $session->status->value !== 'abierta') {
            throw NoActiveCashSessionException::forCreditPayment(); // 409 (ERR-08B).
        }

        $rate = $this->resolveRate((int) $session->business_id, $currency, $exchangeRate);

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'user_id' => Auth::id(),   // Responsable = quien cobra (no-repudio).
            'type' => 'ingreso',    // El cast a CashMovementType convierte el string.
            'category' => 'cobro_credito',
            'payment_method' => 'efectivo',   // Solo efectivo genera movimiento de caja.
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $rate,
            'sale_id' => null,         // No proviene de una venta; es cobro de CxC.
            'authorized_by' => null,         // Un ingreso no requiere autorizante.
            'description' => $reference !== null
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
