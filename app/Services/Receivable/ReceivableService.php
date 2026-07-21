<?php

namespace App\Services\Receivable;

use App\Enums\AccountReceivableStatus;
use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\PaymentMethod;
use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\NoActiveCashSessionException;
use App\Exceptions\OverpaymentException;
use App\Models\AccountReceivable;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ReceivablePayment;
use App\Services\Cash\CashService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReceivableService
{
    public function __construct(private readonly CashService $cash)
    {
    }

    /**
     * RF-08-02: valida el límite de crédito ANTES de facturar. Operación PURA (solo lectura).
     * exposición = Σ balances de CxC pendientes/parciales/vencidas + nueva venta.
     * Si excede y no hay autorización ROL-01, rechaza. Se llama desde InvoiceService.
     *
     * @throws CreditLimitExceededException
     */
    public function assertCreditAvailable(Customer $customer, string $newAmount, bool $authorizedByOwner = false): void
    {
        $limit = (string) $customer->credit_limit;

        // credit_limit = 0 → el cliente no opera a crédito (RF-08-02).
        if (bccomp($limit, '0', 2) <= 0) {
            throw new DomainException('El cliente no tiene línea de crédito (credit_limit = 0).');
        }

        $exposure = $this->currentExposure($customer);
        $projected = bcadd($exposure, $newAmount, 2);

        if (bccomp($projected, $limit, 2) > 0 && ! $authorizedByOwner) {
            throw new CreditLimitExceededException($projected, $limit);
        }
    }

    /**
     * RF-08-01: genera la CxC de una factura a crédito. Se invoca DENTRO de la transacción
     * de emisión de InvoiceService (atomicidad factura⇄CxC: si una falla, ninguna persiste).
     */
    public function generarDesdeFactura(Invoice $invoice): AccountReceivable
    {
        if ($invoice->payment_type !== InvoicePaymentType::Credito) {
            throw new DomainException('Solo una factura a crédito genera cuenta por cobrar.');
        }

        $paid = (string) $invoice->paid_amount;   // puede traer un pago inicial parcial

        $ar = new AccountReceivable([
            'customer_id'  => $invoice->customer_id,
            'invoice_id'   => $invoice->id,
            'total_amount' => (string) $invoice->total,
            'paid_amount'  => $paid,
            'status'       => bccomp($paid, '0', 2) > 0
                ? AccountReceivableStatus::Parcial     // nace parcial si hubo abono inicial
                : AccountReceivableStatus::Pendiente,  // si no, pendiente
            'due_date'     => $invoice->issued_at?->copy()->addDays(30),  // plazo por defecto (parametrizable)
        ]);
        $ar->save();   // balance lo deriva el motor

        return $ar;
    }

    /**
     * RF-08-03: abono atómico de 5 pasos. Registra el pago, actualiza la CxC, sincroniza
     * la factura y —si entra por caja— genera el movimiento 'cobro_credito'.
     *
     * @throws OverpaymentException|NoActiveCashSessionException
     */
    public function registrarAbono(
        int $accountReceivableId,
        string $amount,
        PaymentMethod $method,
        int $userId,
        ?int $cashSessionId = null,
        ?string $reference = null,
    ): ReceivablePayment {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new DomainException('El abono debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($accountReceivableId, $amount, $method, $userId, $cashSessionId, $reference) {
            // lockForUpdate: serializa dos abonos concurrentes sobre la misma CxC.
            $ar = AccountReceivable::query()->whereKey($accountReceivableId)->lockForUpdate()->firstOrFail();

            // Tope: nunca abonar más que el saldo (ERR-08 sobre-abono). Backstop legible antes del motor.
            if (bccomp($amount, (string) $ar->balance, 2) > 0) {
                throw new OverpaymentException("El abono ({$amount}) excede el saldo pendiente ({$ar->balance}).");
            }

            // Abono en efectivo → exige caja activa (ERR-08B). Reusa la excepción de MOD-06.
            if ($method->affectsCashDrawer()) {
                $this->assertActiveCashSession($cashSessionId);
            }

            // (1) Insertar el abono (append-only).
            $payment = ReceivablePayment::create([
                'accounts_receivable_id' => $ar->id,
                'cash_session_id'        => $cashSessionId,
                'user_id'                => $userId,
                'amount'                 => $amount,
                'payment_method'         => $method,
                'reference'              => $reference,
                'paid_at'                => now(),
            ]);

            // (2) Subir paid_amount (el motor recalcula balance solo).
            $ar->paid_amount = bcadd((string) $ar->paid_amount, $amount, 2);

            // (3+4) Sincronizar estado CxC ⇄ factura (RF-08-04).
            $this->syncStatuses($ar);
            $ar->save();

            // (5) Reflejo en caja: movimiento 'cobro_credito' solo si es efectivo.
            if ($method->affectsCashDrawer()) {
                $this->cash->registrarMovimiento(
                    sessionId:     $cashSessionId,
                    type:          CashMovementType::Ingreso,
                    category:      CashMovementCategory::CobroCredito,
                    paymentMethod: $method,
                    amount:        $amount,
                    userId:        $userId,
                    description:   'Cobro de crédito CxC #'.$ar->id,
                );
            }

            return $payment;
        });
    }

    /**
     * RF-08-07: reversión por anulación/devolución. Reduce el balance por el monto de la
     * nota de crédito. Los abonos ya recibidos NO se borran (BR-07); el resarcimiento del
     * excedente ya pagado se resuelve en MOD-10. Se invoca desde InvoiceService::anular.
     */
    public function revertirPorAnulacion(Invoice $invoice, int $authorizerId): void
    {
        $ar = $invoice->accountReceivable;
        if ($ar === null) {
            return;   // factura de contado: nada que revertir
        }

        DB::transaction(function () use ($ar) {
            // Anulación total: la deuda pendiente desaparece llevando total al monto ya pagado,
            // dejando balance 0 y estado trazable (nunca borrado físico — BR-04/BR-07).
            $ar->total_amount = (string) $ar->paid_amount;   // balance generado → 0
            $ar->status = AccountReceivableStatus::Pagada;   // saldada por anulación, trazable
            $ar->save();
            // Los ReceivablePayment permanecen intactos (evidencia). El excedente ya abonado
            // se resarce en MOD-10 (reembolso o saldo a favor).
        });
    }

    /** RF-10-03: reduce el balance de la CxC por una nota de crédito (sin borrar abonos, BR-07). */
    public function reducirPorNotaCredito(\App\Models\AccountReceivable $ar, string $amount): void
    {
        DB::transaction(function () use ($ar, $amount) {
            $ar->refresh();
            // Reducir el total (balance generado baja). Nunca por debajo de lo ya pagado.
            $nuevoTotal = bcsub((string) $ar->total_amount, $amount, 2);
            if (bccomp($nuevoTotal, (string) $ar->paid_amount, 2) < 0) {
                $nuevoTotal = (string) $ar->paid_amount;   // piso: no dejar balance negativo
            }
            $ar->total_amount = $nuevoTotal;
            $this->syncStatuses($ar);   // reutiliza la sincronización CxC⇄factura de MOD-08
            $ar->save();
        });
    }

    /**
     * RF-08-05: cron que marca 'vencida' toda CxC con due_date pasada y balance > 0.
     * Retorna cuántas marcó (para el log del job y la alerta de MOD-11).
     */
    public function marcarVencidas(int $businessId): int
    {
        return AccountReceivable::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [AccountReceivableStatus::Pendiente->value, AccountReceivableStatus::Parcial->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->where('balance', '>', 0)
            ->update(['status' => AccountReceivableStatus::Vencida->value]);
        // TODO (MOD-11): por cada marcada, AnomalyService->registrar('cuenta_vencida', $ar).
    }

    // ─────── Helpers privados ───────

    /** Exposición = Σ balances de CxC que aún cuentan (pendiente/parcial/vencida). */
    private function currentExposure(Customer $customer): string
    {
        $sum = AccountReceivable::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                AccountReceivableStatus::Pendiente->value,
                AccountReceivableStatus::Parcial->value,
                AccountReceivableStatus::Vencida->value,
            ])
            ->sum('balance');

        return bcadd('0.00', (string) $sum, 2);
    }

    /** Sincroniza CxC y factura: nunca una pagada con la otra pendiente (RF-08-04). */
    private function syncStatuses(AccountReceivable $ar): void
    {
        // balance recién actualizado: lo recalculamos en PHP para decidir estado
        // (el valor del motor se refresca tras save; aquí usamos total - paid vigente).
        $balance = bcsub((string) $ar->total_amount, (string) $ar->paid_amount, 2);

        if (bccomp($balance, '0', 2) === 0) {
            $ar->status = AccountReceivableStatus::Pagada;
            $invoiceStatus = InvoicePaymentStatus::Pagada;
        } elseif (bccomp((string) $ar->paid_amount, '0', 2) > 0) {
            $ar->status = AccountReceivableStatus::Parcial;
            $invoiceStatus = InvoicePaymentStatus::Parcial;
        } else {
            $ar->status = AccountReceivableStatus::Pendiente;
            $invoiceStatus = InvoicePaymentStatus::Pendiente;
        }

        // Sincroniza la factura en la MISMA transacción. El guard de Invoice permite
        // mutar payment_status y paid_amount (no el núcleo fiscal).
        $invoice = $ar->invoice;
        $invoice->paid_amount    = (string) $ar->paid_amount;
        $invoice->payment_status = $invoiceStatus;
        $invoice->save();
    }

    private function assertActiveCashSession(?int $cashSessionId): void
    {
        if ($cashSessionId === null) {
            throw new NoActiveCashSessionException('El abono en efectivo requiere una sesión de caja activa.');
        }
        $open = CashSession::query()->whereKey($cashSessionId)->where('status', 'abierta')->exists();
        if (! $open) {
            throw new NoActiveCashSessionException('La sesión de caja indicada no está abierta.');
        }
    }
}
