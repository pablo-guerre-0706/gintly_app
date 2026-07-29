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
use App\Models\Invoice;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Warehouse;
use App\Services\Cash\CashService;
use App\Services\Receivable\ReceivableService;
use App\Services\Inventory\InventoryService;
use App\Support\FolioGenerator;
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
            $invoice = Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();

            $taxRate        = (string) $invoice->business->tax_rate; // Tasa vigente del negocio (RF-10-04).
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

            $totalReturned = '0.00';
            $taxableBase   = '0.00';

            foreach ($data['lines'] as $line) {
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

                $unitPrice = (string) $saleItem->unit_price; // Precio congelado (RF-10-01).
                $lineTotal = bcmul($qty, $unitPrice, 2);

                $totalReturned = bcadd($totalReturned, $lineTotal, 2);
                if ($saleItem->is_taxable) {
                    $taxableBase = bcadd($taxableBase, $lineTotal, 2); // Base gravable congelada (RF-10-04).
                }

                // Destino físico (RF-10-02).
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
                    'unit_price'      => $unitPrice,
                    'destination'     => $destination->value,
                    'reason_code'     => $reason->value,
                    'line_total'      => $lineTotal,
                ]);
            }

            $return->total_returned = $totalReturned;
            $return->status         = SalesReturnStatus::Procesada;
            $return->save();

            // IVA proporcional revertido (RF-10-04) + total resarcido.
            $taxAmount = bcmul($taxableBase, $taxRate, 2);
            $ncTotal   = bcadd($totalReturned, $taxAmount, 2);

            $resolution = $this->resolverResarcimiento($invoice, $data['cash_session_id'] ?? null, $ncTotal);

            $this->aplicarResarcimiento($resolution, $invoice, $ncTotal, $data['cash_session_id'] ?? null, $return->code);

            $this->emitirNotaCredito($invoice, $return, $resolution, $ncTotal, $taxAmount, $data['cash_session_id'] ?? null);

            return $return->fresh(['items', 'creditNote']);
        });
    }

    // =====================================================================
    // RF-10-03 · Saldo a favor del cliente (Fase 1: NC de saldo como fuente de verdad).
    // =====================================================================
    /** @return array{customer_id:int, available_credit_balance:string, open_credit_notes:\Illuminate\Support\Collection} */
    public function saldoAFavor(\App\Models\Customer $customer): array
    {
        $openNotes = CreditNote::query()
            ->where('customer_id', $customer->id)
            ->where('resolution_type', CreditNoteResolutionType::NotaCreditoSaldo->value)
            ->where('status', CreditNoteStatus::Emitida->value)
            ->latest('issued_at')
            ->get();

        $balance = $openNotes->reduce(
            static fn (string $carry, CreditNote $n): string => bcadd($carry, (string) $n->total_amount, 2),
            '0.00'
        );

        return [
            'customer_id'              => $customer->id,
            'available_credit_balance' => $balance,
            'open_credit_notes'        => $openNotes,
        ];
    }

    // ---------------- Helpers ----------------

    private function resolverResarcimiento(Invoice $invoice, ?int $cashSessionId, string $ncTotal): CreditNoteResolutionType
    {
        $cxc = $invoice->accountReceivable; // P7 (MOD-08).

        // Crédito con saldo vivo ⇒ la NC reduce PRIMERO la deuda (RF-10-03).
        if ($invoice->payment_type->value === 'credito'
            && $cxc !== null
            && bccomp((string) $cxc->balance, '0.00', 2) > 0
        ) {
            if ($cashSessionId !== null) {
                throw InvalidRefundMethodException::cashOnUnpaidCredit(); // ERR-10B.
            }

            return CreditNoteResolutionType::ReduccionCxc;
        }

        // Contado pagado (o crédito ya saldado): efectivo si se aportó caja, si no saldo a favor.
        return $cashSessionId !== null
            ? CreditNoteResolutionType::ReembolsoEfectivo
            : CreditNoteResolutionType::NotaCreditoSaldo;
    }

    private function aplicarResarcimiento(
        CreditNoteResolutionType $resolution,
        Invoice $invoice,
        string $ncTotal,
        ?int $cashSessionId,
        string $returnCode
    ): void {
        switch ($resolution) {
            case CreditNoteResolutionType::ReduccionCxc:
                $this->receivables->reducirPorNotaCredito($invoice->accountReceivable, $ncTotal);
                break;

            case CreditNoteResolutionType::ReembolsoEfectivo:
                // Autoridad ROL-01 (BR-06), verificada en la capa de servicios.
                if (Auth::user() === null || ! Auth::user()->can('refundCash', CreditNote::class)) {
                    throw new RefundAuthorizationException();
                }
                $this->cash->registrarReembolsoDevolucion((int) $cashSessionId, $ncTotal, (int) Auth::id(), $returnCode);
                break;

            case CreditNoteResolutionType::NotaCreditoSaldo:
                // La propia NC de saldo es la fuente de verdad del crédito disponible (Fase 1). Sin efecto extra.
                break;
        }
    }

    private function emitirNotaCredito(
        Invoice $invoice,
        SalesReturn $return,
        CreditNoteResolutionType $resolution,
        string $ncTotal,
        string $taxAmount,
        ?int $cashSessionId
    ): CreditNote {
        $note = new CreditNote();
        $note->invoice_id      = $invoice->id;
        $note->sales_return_id = $return->id; // unique ⇒ una devolución, una NC.
        $note->customer_id     = $invoice->customer_id;
        $note->cash_session_id = $resolution === CreditNoteResolutionType::ReembolsoEfectivo ? $cashSessionId : null;
        $note->issued_by       = Auth::id();
        $note->folio           = $this->folios->next($invoice->business_id, DocumentSequenceType::CreditNote); // Fiscal, prefijo NC-.
        $note->resolution_type = $resolution;
        $note->total_amount    = $ncTotal;
        $note->tax_amount      = $taxAmount;
        $note->status          = CreditNoteStatus::Emitida;
        $note->issued_at       = now();
        $note->save();

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
