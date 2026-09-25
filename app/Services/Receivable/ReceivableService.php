<?php

declare(strict_types=1);

namespace App\Services\Receivable;

use App\Enums\AccountReceivableStatus;
use App\Enums\InvoicePaymentStatus;
use App\Exceptions\InvoiceVoidedException;
use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\OverpaymentException;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\ReceivablePayment;
use App\Services\Anomaly\AnomalyService;
use App\Services\Cash\CashService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ReceivableService
{
    /** Plazo de crédito por defecto en días (RF-08-01). Parametrizable por negocio en Fase 2. */
    private const DEFAULT_CREDIT_TERM_DAYS = 30;

    public function __construct(
        private readonly CashService $cashService,
        private readonly AnomalyService $anomalyService
    ) {
    }

    // =====================================================================
    // Generación de la CxC al facturar (IN-TX, lo llama InvoiceService)
    // =====================================================================
    public function generarDesdeFactura(Invoice $invoice): AccountReceivable
    {
        $total = (string) $invoice->total;
        $paid  = (string) $invoice->paid_amount; // Pago inicial parcial, si lo hubo.

        $ar = new AccountReceivable();
        $ar->customer_id  = $invoice->customer_id;
        $ar->invoice_id   = $invoice->id;
        $ar->total_amount = $total;
        $ar->paid_amount  = $paid;
        // Plazo por defecto: emisión + N días. copy() evita mutar la fecha de la factura.
        $ar->due_date     = $invoice->issued_at->copy()
            ->addDays(self::DEFAULT_CREDIT_TERM_DAYS)
            ->toDateString();
        // status FUERA de fillable: se deriva de los montos (nunca 'vencida').
        $ar->status = AccountReceivableStatus::fromAmounts($total, $paid);
        $ar->save(); // business_id lo inyecta el trait. unique(invoice_id) garantiza la unicidad 1:1.

        // Materializa el asiento de CARTERA del pago inicial: por cada invoice_payment de la
        // factura (fuente fiscal) crea su receivable_payment enlazado 1:1. NO vuelve a
        // incrementar paid_amount (ya viene del total pagado de la factura, arriba) ni asienta
        // caja (el cash_movement 'venta' ya se registró al emitir). Garantiza la invariante
        // Σ receivable_payments == accounts_receivable.paid_amount == Σ invoice_payments.
        foreach ($invoice->payments()->get() as $invoicePayment) {
            $this->materializarAbonoInicial($ar, $invoicePayment);
        }

        return $ar;
    }

    /**
     * Crea el receivable_payment que respalda un invoice_payment ya existente (pago inicial),
     * copiando sus datos y enlazándolo 1:1, SIN tocar acumulados ni caja.
     */
    private function materializarAbonoInicial(AccountReceivable $ar, InvoicePayment $invoicePayment): ReceivablePayment
    {
        $payment = new ReceivablePayment();
        $payment->accounts_receivable_id = $ar->id;
        $payment->invoice_payment_id     = $invoicePayment->id;
        $payment->cash_session_id        = $invoicePayment->cash_session_id;
        $payment->amount                 = (string) $invoicePayment->amount;
        $payment->payment_method         = $invoicePayment->payment_method;
        $payment->reference              = $invoicePayment->reference;
        $payment->paid_at                = $invoicePayment->paid_at;
        $payment->user_id                = $invoicePayment->user_id; // No-repudio: el cajero del cobro inicial.
        $payment->save();

        return $payment;
    }

    // =====================================================================
    // Validación preventiva de cupo (PRE-TX, solo lectura)
    // =====================================================================
    public function assertCreditAvailable(Customer $customer, string $amount, bool $ownerAuthorized = false): void
    {
        $limit = (string) $customer->credit_limit;

        // Cliente sin línea de crédito: bloqueado incondicionalmente.
        if (bccomp($limit, '0.00', 2) <= 0) {
            throw CreditLimitExceededException::noCreditLine($limit);
        }

        $exposure  = $this->exposicion($customer);
        $projected = bcadd($exposure, $amount, 2);

        // Excede el cupo y no hay autorización ROL-01 ⇒ rechazo con cifras auditables.
        if (bccomp($projected, $limit, 2) > 0 && ! $ownerAuthorized) {
            throw CreditLimitExceededException::overLimit($exposure, $limit);
        }
    }

    /**
     * (endpoint credit-check): evaluación PURA, no escribe.
     * @return array{approved:bool,exposure:string,limit:string,available:string,requires_owner_authorization:bool}
     */
    public function evaluarCredito(Customer $customer, string $amount): array
    {
        $limit     = (string) $customer->credit_limit;
        $exposure  = $this->exposicion($customer);
        $projected = bcadd($exposure, $amount, 2);

        $hasLine = bccomp($limit, '0.00', 2) > 0;
        $fits    = $hasLine && bccomp($projected, $limit, 2) <= 0;

        return [
            'approved'                     => $fits,
            'exposure'                     => $exposure,
            'limit'                        => $limit,
            'available'                    => $this->creditoDisponible($limit, $exposure),
            'requires_owner_authorization' => $hasLine && ! $fits,
        ];
    }

    // =====================================================================
    // Estado de crédito consolidado (solo lectura)
    // =====================================================================
    /** @return array<string, mixed> */
    public function estadoDeCredito(Customer $customer): array
    {
        $limit    = (string) $customer->credit_limit;
        $exposure = $this->exposicion($customer);

        $openAccounts = $customer->accountsReceivable()
            ->pending()
            ->orderBy('due_date')
            ->get();

        // Historial = TODOS los abonos de TODAS las cuentas del cliente (abiertas o saldadas).
        $arIds   = $customer->accountsReceivable()->pluck('id');
        $history = ReceivablePayment::query()
            ->whereIn('accounts_receivable_id', $arIds)
            ->with('user:id,name')
            ->latest('paid_at')
            ->get();

        return [
            'credit_limit'     => $limit,
            'exposure'         => $exposure,
            'available_credit' => $this->creditoDisponible($limit, $exposure),
            'open_accounts'    => $openAccounts,
            'payment_history'  => $history,
        ];
    }

    // =====================================================================
    // Abono atómico de 5 pasos (método transaccional canónico)
    // =====================================================================
    /** @param array{amount:string|float, payment_method:string, cash_session_id?:int|null, reference?:string|null} $data */
    public function abonar(AccountReceivable $accountReceivable, array $data): ReceivablePayment
    {
        $amount = (string) $data['amount'];
        $method = (string) $data['payment_method'];

        return DB::transaction(function () use ($accountReceivable, $data, $amount, $method): ReceivablePayment {

            // Orden de bloqueo canónico Invoice → CxC (idéntico a InvoiceService::anular): elimina el ABBA.
            $invoice = Invoice::query()
                ->whereKey($accountReceivable->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Factura anulada ⇒ error de dominio controlado (409), nunca un 500 crudo.
            if ($invoice->status->isVoided()) {
                throw new InvoiceVoidedException();
            }

            // Serializa abonos concurrentes sobre la MISMA cuenta (bloqueo de fila).
            $ar = AccountReceivable::query()
                ->whereKey($accountReceivable->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // No se abona una cuenta ya saldada.
            if ($ar->status->isSettled() || bccomp((string) $ar->balance, '0.00', 2) <= 0) {
                throw new OverpaymentException(
                    (string) $ar->balance,
                    $amount,
                    'La cuenta ya está saldada; no admite más abonos.'
                );
            }

            // El abono no puede exceder el saldo (validación de servicio; el motor la respalda con el CHECK).
            if (bccomp($amount, (string) $ar->balance, 2) > 0) {
                throw new OverpaymentException((string) $ar->balance, $amount);
            }

            // Paso 5 adelantado para FALLAR RÁPIDO si la caja está cerrada (todo es atómico igual).
            $cashSessionId = $method === 'efectivo' ? (int) $data['cash_session_id'] : null;
            if ($method === 'efectivo') {
                $this->cashService->registrarCobroCredito($cashSessionId, $amount, $data['reference'] ?? null);
            }

            // (1a) Asiento FISCAL del cobro en el libro de la factura (RF-07): todo abono
            //      queda reflejado fiscalmente. invoice_payments es la fuente fiscal única;
            //      invoice.paid_amount se mantiene == Σ invoice_payments (ver syncStatuses).
            $invoicePayment = new InvoicePayment();
            $invoicePayment->invoice_id      = $invoice->id;
            $invoicePayment->cash_session_id = $cashSessionId;
            $invoicePayment->user_id         = Auth::id();
            $invoicePayment->payment_method  = $method;
            $invoicePayment->amount          = $amount;
            $invoicePayment->reference       = $data['reference'] ?? null;
            $invoicePayment->paid_at         = now();
            $invoicePayment->save();

            // (1b) Abono TRAZABLE de la CxC, enlazado 1:1 al asiento fiscal (sin doble conteo).
            //      user_id lo fija el Service (no-repudio), NUNCA el request.
            $payment = new ReceivablePayment();
            $payment->accounts_receivable_id = $ar->id;
            $payment->invoice_payment_id     = $invoicePayment->id;
            $payment->cash_session_id        = $cashSessionId;
            $payment->amount                 = $amount;
            $payment->payment_method         = $method;
            $payment->reference              = $data['reference'] ?? null;
            $payment->paid_at                = now();
            $payment->user_id                = Auth::id();
            $payment->save();

            // (2) Incrementa el pagado; (3) el MOTOR recalcula 'balance' (columna generada).
            $ar->paid_amount = bcadd((string) $ar->paid_amount, $amount, 2);
            $ar->save();
            $ar->refresh(); // Relee el 'balance' recomputado por el motor.

            // (4) Sincroniza estado de cuenta ⇄ estado de pago de la factura (RF-08-04).
            $this->syncStatuses($ar, $amount);

            return $payment->fresh(['accountReceivable', 'user']);
        });
    }

    /**
     * Alias de compatibilidad. Antes existían llamadas a `registrarAbono()`;
     * delega en el método canónico `abonar()`. Elimínalo si unificas el nombre en toda la base.
     * @param array{amount:string|float, payment_method:string, cash_session_id?:int|null, reference?:string|null} $data
     */
    public function registrarAbono(AccountReceivable $accountReceivable, array $data): ReceivablePayment
    {
        return $this->abonar($accountReceivable, $data);
    }

    // =====================================================================
    // Reversión por anulación (IN-TX, lo llama InvoiceService::anular)
    // =====================================================================
    public function revertirPorAnulacion(AccountReceivable $accountReceivable): void
    {
        $ar = AccountReceivable::query()
            ->whereKey($accountReceivable->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        // Salda llevando el total al monto ya pagado ⇒ balance 0 (RF-08-07).
        // paid_amount se conserva VERAZ (== Σ abonos) para el resarcimiento en MOD-10.
        // Los ReceivablePayment NO se eliminan: permanecen como evidencia (BR-07).
        // (Requiere chk_ar_total_positive >= 0 para el caso paid = 0.)
        $ar->total_amount = (string) $ar->paid_amount;
        $ar->status       = AccountReceivableStatus::Pagada;
        $ar->save();
    }

    // =====================================================================
    // MOD-10 · Reducción por nota de crédito
    // =====================================================================
    /**
     * Reduce el saldo de la CxC por una nota de crédito, sin borrar abonos.
     * El total baja por el monto, nunca por debajo de lo ya abonado. Re-sincroniza estados.
     * Debe correr dentro de la transacción del retorno (ReturnService).
     */
    public function reducirPorNotaCredito(AccountReceivable $accountReceivable, string $amount): void
    {
        // Orden de bloqueo CANÓNICO Invoice → AccountReceivable (idéntico a abonar/anular):
        // se bloquea primero la factura para prevenir un ABBA con el flujo futuro de MOD-10
        // que invoca este método. syncStatuses reentra el mismo lock de la factura.
        Invoice::query()
            ->whereKey($accountReceivable->invoice_id)
            ->lockForUpdate()
            ->firstOrFail();

        $ar = AccountReceivable::query()
            ->whereKey($accountReceivable->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        // El total no puede quedar por debajo de lo abonado: saldo mínimo 0.
        $newTotal = bcsub((string) $ar->total_amount, $amount, 2);
        if (bccomp($newTotal, (string) $ar->paid_amount, 2) < 0) {
            $newTotal = (string) $ar->paid_amount;
        }
        $ar->total_amount = $newTotal;
        $ar->save();
        $ar->refresh(); // Relee 'balance' recomputado por el motor.

        // Re-deriva SIN sumar abono: pasa amount '0.00' para no tocar invoice.paid_amount.
        $this->syncStatuses($ar, '0.00');
    }

    // =====================================================================
    // Marcado de vencidas (CRON, sin sesión de usuario)
    // =====================================================================
    public function marcarVencidas(int $businessId): int
    {
        return DB::transaction(function () use ($businessId): int {
            $affected = 0;

            AccountReceivable::query()
                ->withoutGlobalScopes() // El cron corre sin tenant resuelto: filtra explícito.
                ->where('business_id', $businessId)
                ->overdue()
                ->lockForUpdate()
                ->get()
                ->each(function (AccountReceivable $ar) use (&$affected): void {
                    $ar->status = AccountReceivableStatus::Vencida;
                    $ar->save();
                    $affected++;

                    // Alerta 'cuenta_vencida' → AnomalyService (MOD-11).
                    $this->anomalyService->registrarSilencioso('cuenta_vencida', $ar);                    }
                );

            return $affected;
        });
    }

    // ---------------- Helpers ----------------

    /** Exposición = Σ balances de cuentas pendientes/parciales/vencidas del cliente. */
    public function exposicion(Customer $customer): string
    {
        $sum = $customer->accountsReceivable()->pending()->sum('balance');

        return bcadd((string) $sum, '0.00', 2); // Normaliza a escala 2.
    }

    private function creditoDisponible(string $limit, string $exposure): string
    {
        $available = bcsub($limit, $exposure, 2);

        // Piso en cero (RF-08-06): nunca crédito disponible negativo.
        return bccomp($available, '0.00', 2) < 0 ? '0.00' : $available;
    }

    /**
     * Derivación única de estados (reutilizada por abono y por nota de crédito).
     * $paymentDelta se suma a invoice.paid_amount SOLO cuando proviene de un abono real;
     * en la reducción por NC se pasa '0.00' porque el estado se acopla al saldo YA reducido.
     */
    private function syncStatuses(AccountReceivable $ar, string $paymentDelta): void
    {
        // --- Estado de la CUENTA ---
        $derived = AccountReceivableStatus::fromAmounts((string) $ar->total_amount, (string) $ar->paid_amount);

        if ($derived->isSettled()) {
            $ar->status = AccountReceivableStatus::Pagada;          // Saldar PRIMA incluso sobre 'vencida'.
        } elseif ($ar->status !== AccountReceivableStatus::Vencida) {
            $ar->status = $derived;                                 // 'vencida' solo la gestiona el cron.
        }
        $ar->save();

        // --- Estado de pago de la FACTURA (permitido por la inmutabilidad parcial D-29) ---
        $invoice = $ar->invoice()->lockForUpdate()->first();
        if ($invoice instanceof Invoice) {
            if (bccomp($paymentDelta, '0.00', 2) > 0) {
                // Abono real: suma al pagado de la factura y deriva del total FISCAL.
                // fromAmounts(paid, total): el orden es (pagado, total), NO (total, pagado).
                $invoice->paid_amount    = bcadd((string) $invoice->paid_amount, $paymentDelta, 2);
                $invoice->payment_status = InvoicePaymentStatus::fromAmounts(
                    (string) $invoice->paid_amount,
                    (string) $invoice->total
                );
            } else {
                // Reducción por NC: la factura acopla su estado al saldo REDUCIDO de la CxC.
                // fromAmounts(paid, total): pagado primero, total (reducido) después.
                $invoice->payment_status = InvoicePaymentStatus::fromAmounts(
                    (string) $ar->paid_amount,
                    (string) $ar->total_amount
                );
            }
            $invoice->save();
        }
    }
}
