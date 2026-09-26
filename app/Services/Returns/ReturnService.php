<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Enums\CreditNoteResolutionType;
use App\Enums\CreditNoteStatus;
use App\Enums\DocumentSequenceType;
use App\Enums\RoleName;
use App\Enums\SalesReturnStatus;
use App\Enums\ReturnDestination;
use App\Enums\ReturnReasonCode;
use App\Exceptions\InvalidRefundMethodException;
use App\Exceptions\RefundAuthorizationException;
use App\Exceptions\ReturnQuantityException;
use App\Models\CreditNote;
use App\Models\CreditNoteResolution;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Warehouse;
use App\Services\Cash\CashService;
use App\Services\Receivable\ReceivableService;
use App\Services\Inventory\InventoryService;
use App\Support\FolioGenerator;
use App\Support\Money;
use App\Support\SequenceGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ReturnService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CashService $cash,
        private readonly ReceivableService $receivables,
        private readonly SequenceGenerator $sequences, // Folio interno DV-.
        private readonly FolioGenerator $folios,       // Folio fiscal NC- (document_sequences).
    ) {
    }

    // =====================================================================
    // RF-10-01…04 · Procesa la devolución completa de forma atómica.
    // =====================================================================
    /** @param array{invoice_id:int, cash_session_id?:int|null, notes?:string|null, lines:array<int,array{sale_item_id:int, quantity:string, reason_code:string, destination?:string|null, warehouse_id?:int|null}>} $data */
    public function registrar(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data): SalesReturn {
            // ORDEN DE LOCK CANÓNICO (compatible con MOD-07/08/09): (1) Invoice → (2) SaleItems por
            // id asc → (3) Stock (en el reingreso) → (4) CxC → (5) Caja → (6) NotaCredito.
            $invoice = Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();

            $invoiceSaleIds = $invoice->sales()->pluck('sales.id');
            $defaultWh      = $this->bodegaDefault($invoice->branch_id);

            $return = new SalesReturn();
            $return->branch_id   = $invoice->branch_id;
            $return->invoice_id  = $invoice->id;
            $return->customer_id = $invoice->customer_id;
            $return->notes       = $data['notes'] ?? null;
            $return->user_id     = Auth::id();
            $return->code        = $this->sequences->next($invoice->business_id, 'sales_return', 'DV-');
            $return->status      = SalesReturnStatus::Registrada;
            $return->returned_at = now();
            $return->save();

            // Líneas en orden determinista por sale_item_id (serializa y evita ABBA).
            $orderedLines = collect($data['lines'])
                ->sortBy(static fn (array $l): int => (int) $l['sale_item_id'])
                ->values();

            $totalReturned = '0.00'; // Σ neto congelado (incluye descuento de línea).
            $ncTax         = '0.00'; // Σ IVA congelado proporcional.

            foreach ($orderedLines as $line) {
                $saleItem = SaleItem::query()->whereKey($line['sale_item_id'])->lockForUpdate()->firstOrFail();
                $qty      = (string) $line['quantity'];

                if (! $invoiceSaleIds->contains($saleItem->sale_id)) {
                    throw new ReturnQuantityException($saleItem->id, '0.000', $qty); // Línea ajena a la factura.
                }

                // Devolvible = entregado − ya devuelto (ERR-10). Nunca contra lo facturado.
                $returnable = bcsub((string) $saleItem->dispatched_quantity, (string) $saleItem->returned_quantity, 3);
                if (bccomp($qty, $returnable, 3) > 0) {
                    throw new ReturnQuantityException($saleItem->id, $returnable, $qty);
                }

                $reason      = ReturnReasonCode::from($line['reason_code']);
                $destination = isset($line['destination']) && $line['destination'] !== null
                    ? ReturnDestination::from($line['destination'])
                    : $reason->suggestedDestination();

                // Coherencia motivo⇄destino (defensa de servicio; el request ya la valida).
                if ($destination->isReentry() && ! $reason->allowsReentry()) {
                    $destination = ReturnDestination::Merma;
                }

                $warehouse = isset($line['warehouse_id']) && $line['warehouse_id'] !== null
                    ? Warehouse::query()->whereKey($line['warehouse_id'])->firstOrFail()
                    : $defaultWh;

                // FISCAL (RF-10-04): importes derivados EXCLUSIVAMENTE del snapshot congelado de la
                // línea original (precio, descuento, gravabilidad, tasa, base e IVA), proporcional a
                // la cantidad devuelta. NUNCA se usa la configuración fiscal ni los precios vigentes.
                $frozen = $this->proporcionCongelada($saleItem, $qty);

                $totalReturned = bcadd($totalReturned, $frozen['net'], 2);
                $ncTax         = bcadd($ncTax, $frozen['tax'], 2);

                // Destino físico (RF-10-02). Servicios y no inventariables no reingresan mercancía:
                // ingresarPorDevolucion solo mueve inventario para líneas con insumos/producto físico.
                if ($destination->isReentry()) {
                    $this->inventory->ingresarPorDevolucion($saleItem, $qty, $warehouse, "Devolución {$return->code}");
                } else {
                    $this->inventory->registrarMermaPorDevolucion(
                        $warehouse->id,
                        "Devolución {$return->code} · merma ({$reason->value})"
                    );
                }

                // Acumulado materializado (fuera de fillable). chk_sale_item_return_not_exceed respalda.
                $saleItem->returned_quantity = bcadd((string) $saleItem->returned_quantity, $qty, 3);
                $saleItem->save();

                SalesReturnItem::create([
                    'sales_return_id' => $return->id,
                    'product_id'      => $saleItem->product_id,
                    'sale_item_id'    => $saleItem->id,
                    'warehouse_id'    => $warehouse->id,
                    'quantity'        => $qty,
                    'unit_price'      => (string) $saleItem->unit_price, // Precio congelado (RF-10-01).
                    'destination'     => $destination->value,
                    'reason_code'     => $reason->value,
                    'line_total'      => $frozen['net'],                 // Neto congelado proporcional (con descuento de línea).
                ]);
            }

            $return->total_returned = $totalReturned;
            $return->status         = SalesReturnStatus::Procesada;
            $return->save();

            // Total de la NC = bruto devuelto (neto + IVA congelados) menos el descuento de la
            // factura (nivel comprobante) prorrateado. En una devolución TOTAL de la factura, esto
            // reproduce exactamente el total original (subtotal + IVA − descuento).
            $grossReturned    = bcadd($totalReturned, $ncTax, 2);
            $invoiceDiscount  = $this->descuentoFacturaProrrateado($invoice, $grossReturned);
            $ncTotal          = bcsub($grossReturned, $invoiceDiscount, 2);

            // Plan de resarcimiento normalizado: una o más vías cuya suma == total de la NC.
            $plan = $this->planResarcimiento($invoice, $data['cash_session_id'] ?? null, $ncTotal);

            // Emite la NC (cabecera + desglose trazable) y luego aplica cada vía (CxC / caja / saldo).
            $this->emitirNotaCredito($invoice, $return, $plan, $ncTotal, $ncTax);
            $this->aplicarPlan($plan, $invoice, $return->code);

            return $return->fresh(['items', 'creditNote', 'creditNote.resolutions']);
        });
    }

    // =====================================================================
    // RF-10-03 · Saldo a favor del cliente (Fase 1: NC de saldo como fuente de verdad).
    // =====================================================================
    /** @return array{customer_id:int, available_credit_balance:string, open_credit_notes:\Illuminate\Support\Collection} */
    public function saldoAFavor(Customer $customer): array
    {
        // El saldo a favor es la suma de las VÍAS 'nota_credito_saldo' (no del total de la NC): una
        // NC mixta solo aporta su porción de saldo. Fuente de verdad normalizada (Fase 1).
        $saldoApps = CreditNoteResolution::query()
            ->where('resolution_type', CreditNoteResolutionType::NotaCreditoSaldo->value)
            ->whereHas('creditNote', fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->where('status', CreditNoteStatus::Emitida->value))
            ->with('creditNote')
            ->get();

        $balance = $saldoApps->reduce(
            static fn (string $carry, CreditNoteResolution $r): string => bcadd($carry, (string) $r->amount, 2),
            '0.00'
        );

        $openNotes = $saldoApps
            ->map(static fn (CreditNoteResolution $r): CreditNote => $r->creditNote)
            ->unique('id')
            ->sortByDesc('issued_at')
            ->values();

        return [
            'customer_id'              => $customer->id,
            'available_credit_balance' => $balance,
            'open_credit_notes'        => $openNotes,
        ];
    }

    // ---------------- Helpers ----------------

    /**
     * Importes fiscales de la línea a devolver, derivados EXCLUSIVAMENTE del snapshot congelado
     * en MOD-07 (line_total con descuento de línea, tax_amount, taxable_base). Redondeo ACUMULATIVO
     * (progresivo): el importe de este parcial = acumulado(ya_devuelto + este) − acumulado(ya_devuelto),
     * de modo que (a) la suma de todos los parciales NUNCA excede la figura congelada, (b) al agotar la
     * cantidad devolvible la suma coincide EXACTAMENTE con la figura congelada y (c) el último parcial
     * absorbe cualquier residuo de redondeo. BCMath + Money::round (half-up), nunca float ni config vigente.
     *
     * @return array{net:string, tax:string, base:string}
     */
    private function proporcionCongelada(SaleItem $saleItem, string $qty): array
    {
        $originalQty = (string) $saleItem->quantity;
        $prevQty     = (string) $saleItem->returned_quantity; // Ya devuelto ANTES de este parcial.
        $newQty      = bcadd($prevQty, $qty, 3);

        return [
            'net'  => $this->incrementoCongelado((string) $saleItem->line_total, $prevQty, $newQty, $originalQty),
            'tax'  => $this->incrementoCongelado((string) $saleItem->tax_amount, $prevQty, $newQty, $originalQty),
            'base' => $this->incrementoCongelado((string) $saleItem->taxable_base, $prevQty, $newQty, $originalQty),
        ];
    }

    /** Incremento redondeado = acumulado(newQty) − acumulado(prevQty). */
    private function incrementoCongelado(string $frozenTotal, string $prevQty, string $newQty, string $originalQty): string
    {
        return bcsub(
            $this->acumuladoCongelado($frozenTotal, $newQty, $originalQty),
            $this->acumuladoCongelado($frozenTotal, $prevQty, $originalQty),
            2
        );
    }

    /**
     * Acumulado redondeado de la figura congelada para una cantidad devuelta acumulada. Exacto en 0
     * y en la cantidad total (evita cualquier deriva de escala): devolución completa ⇒ figura congelada.
     */
    private function acumuladoCongelado(string $frozenTotal, string $cumQty, string $originalQty): string
    {
        if (bccomp($cumQty, '0', 3) <= 0) {
            return '0.00';
        }

        if (bccomp($cumQty, $originalQty, 3) >= 0) {
            return Money::round($frozenTotal, 2); // Total devuelto: figura congelada EXACTA.
        }

        return Money::round(bcdiv(bcmul($frozenTotal, $cumQty, 8), $originalQty, 8), 2);
    }

    /**
     * Prorratea el descuento a NIVEL DE FACTURA congelado (invoice.discount_amount) sobre el bruto
     * devuelto, respecto del bruto original de la factura (subtotal + IVA congelados). Cero si la
     * factura no tuvo descuento. Preserva "los descuentos congelados en la factura original" (RF-10-04)
     * y evita resarcir más de lo que el cliente efectivamente debía por esas unidades.
     */
    private function descuentoFacturaProrrateado(Invoice $invoice, string $grossReturned): string
    {
        $discount = (string) $invoice->discount_amount;
        if (bccomp($discount, '0.00', 2) <= 0) {
            return '0.00';
        }

        $invoiceGross = bcadd((string) $invoice->subtotal, (string) $invoice->tax_amount, 2);
        if (bccomp($invoiceGross, '0.00', 2) <= 0) {
            return '0.00';
        }

        return Money::round(bcdiv(bcmul($discount, $grossReturned, 8), $invoiceGross, 8), 2);
    }

    /**
     * Plan de resarcimiento (RF-10-03): una o más VÍAS cuya suma es EXACTAMENTE el total de la NC.
     * Regla: la NC reduce PRIMERO la deuda viva (hasta el saldo pendiente); el EXCEDENTE —que solo
     * puede corresponder a lo ya PAGADO— se reembolsa en efectivo (si se aporta caja) o queda como
     * saldo a favor. Aportar caja sin excedente pagado es ERR-10B (no se reembolsa lo no pagado).
     *
     * @return array<int, array{type: CreditNoteResolutionType, amount: string, cash_session_id: ?int}>
     */
    private function planResarcimiento(Invoice $invoice, ?int $cashSessionId, string $ncTotal): array
    {
        $cxc = $invoice->accountReceivable; // P7 (MOD-08).

        $balance = ($invoice->payment_type->value === 'credito' && $cxc !== null)
            ? (string) $cxc->balance
            : '0.00';

        $plan = [];

        // (1) Reducción de CxC: exactamente por el saldo pendiente (o por el total si es menor).
        $cxcPart = '0.00';
        if (bccomp($balance, '0.00', 2) > 0) {
            $cxcPart = bccomp($ncTotal, $balance, 2) <= 0 ? $ncTotal : $balance;
            $plan[] = ['type' => CreditNoteResolutionType::ReduccionCxc, 'amount' => $cxcPart, 'cash_session_id' => null];
        }

        // (2) Excedente = total NC − reducción de deuda = lo ya pagado que corresponde devolver.
        $excess = bcsub($ncTotal, $cxcPart, 2);
        if (bccomp($excess, '0.00', 2) > 0) {
            $plan[] = $cashSessionId !== null
                ? ['type' => CreditNoteResolutionType::ReembolsoEfectivo, 'amount' => $excess, 'cash_session_id' => $cashSessionId]
                : ['type' => CreditNoteResolutionType::NotaCreditoSaldo, 'amount' => $excess, 'cash_session_id' => null];
        } elseif ($cashSessionId !== null) {
            // Caja aportada pero no hay excedente pagado que reembolsar (crédito no pagado): ERR-10B.
            throw InvalidRefundMethodException::cashOnUnpaidCredit();
        }

        return $plan;
    }

    /**
     * Aplica cada vía del plan. reduccion_cxc reduce la CxC; reembolso_efectivo exige ROL-01 y
     * asienta el egreso; nota_credito_saldo no tiene efecto contable extra (la fila de desglose es
     * la fuente de verdad del saldo a favor).
     *
     * @param array<int, array{type: CreditNoteResolutionType, amount: string, cash_session_id: ?int}> $plan
     */
    private function aplicarPlan(array $plan, Invoice $invoice, string $returnCode): void
    {
        foreach ($plan as $app) {
            switch ($app['type']) {
                case CreditNoteResolutionType::ReduccionCxc:
                    $this->receivables->reducirPorNotaCredito($invoice->accountReceivable, $app['amount']);
                    break;

                case CreditNoteResolutionType::ReembolsoEfectivo:
                    // Autoridad ROL-01 (BR-06), verificada en la capa de servicios.
                    if (Auth::user() === null || ! Auth::user()->can('refundCash', CreditNote::class)) {
                        throw new RefundAuthorizationException();
                    }
                    $this->cash->registrarReembolsoDevolucion(
                        (int) $app['cash_session_id'],
                        $app['amount'],
                        (int) Auth::id(),
                        $returnCode
                    );
                    break;

                case CreditNoteResolutionType::NotaCreditoSaldo:
                    // El saldo a favor lo materializa la fila de desglose (fuente de verdad, Fase 1).
                    break;

                case CreditNoteResolutionType::Mixto:
                    // 'mixto' es solo cabecera; nunca una vía individual del plan.
                    break;
            }
        }
    }

    /**
     * Emite la NC (unique por devolución) y su desglose trazable. La cabecera resolution_type es la
     * vía única, o 'mixto' cuando se aplicaron dos. cash_session_id de cabecera solo si hubo efectivo.
     *
     * @param array<int, array{type: CreditNoteResolutionType, amount: string, cash_session_id: ?int}> $plan
     */
    private function emitirNotaCredito(
        Invoice $invoice,
        SalesReturn $return,
        array $plan,
        string $ncTotal,
        string $taxAmount
    ): CreditNote {
        $header = count($plan) === 1 ? $plan[0]['type'] : CreditNoteResolutionType::Mixto;

        $efectivoSession = null;
        foreach ($plan as $app) {
            if ($app['type'] === CreditNoteResolutionType::ReembolsoEfectivo) {
                $efectivoSession = $app['cash_session_id'];
            }
        }

        $note = new CreditNote();
        $note->invoice_id      = $invoice->id;
        $note->sales_return_id = $return->id; // unique ⇒ una devolución, una NC.
        $note->customer_id     = $invoice->customer_id;
        $note->cash_session_id = $efectivoSession;
        $note->issued_by       = Auth::id();
        $note->folio           = $this->folios->next($invoice->business_id, DocumentSequenceType::CreditNote); // Fiscal, prefijo NC-.
        $note->resolution_type = $header;
        $note->total_amount    = $ncTotal;
        $note->tax_amount      = $taxAmount;
        $note->status          = CreditNoteStatus::Emitida;
        $note->issued_at       = now();
        $note->save();

        // Desglose normalizado: una fila por vía; Σ(amount) == total_amount de la NC.
        foreach ($plan as $app) {
            $note->resolutions()->create([
                'cash_session_id' => $app['cash_session_id'],
                'resolution_type' => $app['type'],
                'amount'          => $app['amount'],
            ]);
        }

        return $note;
    }

    private function bodegaDefault(int $branchId): Warehouse
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->firstOrFail();
    }
}
