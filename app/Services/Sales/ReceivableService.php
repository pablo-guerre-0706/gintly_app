<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\AccountReceivableStatus;
use App\Enums\InvoicePaymentStatus;
use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\OverpaymentException;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ReceivablePayment;
use App\Services\Cash\CashService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ReceivableService
{
    /** Plazo de crédito por defecto en días (RF-08-01). Parametrizable por negocio en Fase 2. */
    private const DEFAULT_CREDIT_TERM_DAYS = 30;

    public function __construct(private readonly CashService $cashService)
    {
    }

    // =====================================================================
    // RF-08-01 · Generación de la CxC al facturar (IN-TX, lo llama InvoiceService)
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
        // Plazo por defecto: emisión + 30 días. copy() evita mutar la fecha de la factura.
        $ar->due_date     = $invoice->issued_at->copy()
            ->addDays(self::DEFAULT_CREDIT_TERM_DAYS)
            ->toDateString();
        // status FUERA de fillable: lo derivamos (nunca 'vencida'). Parcial si hubo pago inicial.
        $ar->status = AccountReceivableStatus::fromAmounts($total, $paid);
        $ar->save(); // business_id lo inyecta el trait. unique(invoice_id) garantiza la unicidad 1:1.

        return $ar;
    }

    // =====================================================================
    // RF-08-02 · Validación preventiva de cupo (PRE-TX, solo lectura)
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
     * RF-08-02 (endpoint credit-check): evaluación PURA, no escribe.
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
            // Cabe con autorización pero no sin ella ⇒ requiere ROL-01.
            'requires_owner_authorization' => $hasLine && ! $fits,
        ];
    }

    // =====================================================================
    // RF-08-06 · Estado de crédito consolidado (solo lectura)
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
    // RF-08-03 · Abono atómico de 5 pasos
    // =====================================================================
    /** @param array{amount:string|float, payment_method:string, cash_session_id?:int|null, reference?:string|null} $data */
    public function abonar(AccountReceivable $accountReceivable, array $data): ReceivablePayment
    {
        $amount = (string) $data['amount'];
        $method = (string) $data['payment_method'];

        return DB::transaction(function () use ($accountReceivable, $data, $amount, $method): ReceivablePayment {
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

            // (1) Inserta el abono. user_id lo fija el Service (no-repudio), NUNCA el request.
            $payment = new ReceivablePayment();
            $payment->accounts_receivable_id = $ar->id;
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
            $this->sincronizarEstados($ar, $amount);

            return $payment->fresh(['accountReceivable', 'user']);
        });
    }

    // =====================================================================
    // RF-08-07 · Reversión por anulación (IN-TX, lo llama InvoiceService::anular)
    // =====================================================================
    public function revertirPorAnulacion(AccountReceivable $accountReceivable): void
    {
        $ar = AccountReceivable::query()
            ->whereKey($accountReceivable->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        // Salda llevando el total al monto ya pagado ⇒ balance 0 (RF-08-07).
        // paid_amount se conserva VERAZ (== Σ abonos) para que MOD-10 resuelva el excedente.
        // Los ReceivablePayment NO se eliminan: permanecen como evidencia (BR-07).
        // (Requiere chk_ar_total_positive >= 0 para el caso paid = 0.)
        $ar->total_amount = (string) $ar->paid_amount;
        $ar->status       = AccountReceivableStatus::Pagada; // Estado terminal trazable.
        $ar->save();
    }

    // =====================================================================
    // RF-08-05 · Marcado de vencidas (CRON, sin sesión de usuario)
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

                    // Alerta 'cuenta_vencida' → AnomalyService (MOD-11). Inerte hasta que exista.
                    if (class_exists(\App\Services\Anomalies\AnomalyService::class)) {
                        app(\App\Services\Anomalies\AnomalyService::class)->registrarCuentaVencida($ar);
                    }
                });

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

    private function sincronizarEstados(AccountReceivable $ar, string $amount): void
    {
        // --- Estado de la CUENTA ---
        $derived = AccountReceivableStatus::fromAmounts(
            (string) $ar->total_amount,
            (string) $ar->paid_amount
        );

        if ($derived->isSettled()) {
            $ar->status = AccountReceivableStatus::Pagada;          // Saldar PRIMA incluso sobre 'vencida'.
        } elseif ($ar->status !== AccountReceivableStatus::Vencida) {
            $ar->status = $derived;                                 // 'vencida' solo la gestiona el cron.
        }
        $ar->save();

        // --- Estado de pago de la FACTURA (permitido por el guarda de inmutabilidad parcial D-29) ---
        $invoice = $ar->invoice()->lockForUpdate()->first();
        if ($invoice instanceof Invoice) {
            $invoice->paid_amount    = bcadd((string) $invoice->paid_amount, $amount, 2);
            $invoice->payment_status = InvoicePaymentStatus::fromAmounts(
                (string) $invoice->total,
                (string) $invoice->paid_amount
            );
            $invoice->save(); // Nunca puede haber cuenta pagada con factura pendiente (RF-08-04).
        }
    }
}
