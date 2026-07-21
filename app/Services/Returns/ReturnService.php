<?php

namespace App\Services\Returns;

use App\Enums\CreditNoteResolutionType;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\ReturnDestination;
use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Exceptions\InvalidRefundMethodException;
use App\Exceptions\ReturnQuantityException;
use App\Models\CreditNote;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\InventoryAdjustment;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Services\Cash\CashService;
use App\Services\Inventory\InventoryService;
use App\Services\Receivable\ReceivableService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CashService $cash,
        private readonly ReceivableService $receivable,
    ) {
    }

    /**
     * Procesa una devolución completa (RF-10-01/02/03): valida saldo devolvible, decide destino
     * físico (reingreso/merma), genera UNA nota de crédito y resarce por la vía correcta. Atómico.
     *
     * @throws ReturnQuantityException|InvalidRefundMethodException
     */
    public function procesar(int $invoiceId, array $lines, int $userId, ?int $cashSessionId = null, ?string $notes = null): SalesReturn
    {
        return DB::transaction(function () use ($invoiceId, $lines, $userId, $cashSessionId, $notes) {
            $invoice = Invoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            $return = SalesReturn::create([
                'branch_id'      => $invoice->branch_id,
                'invoice_id'     => $invoice->id,
                'customer_id'    => $invoice->customer_id,
                'user_id'        => $userId,
                'code'           => 'DEV-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
                'total_returned' => '0.00',
                'returned_at'    => now(),
                'notes'          => $notes,
            ]);

            $totalReturned = '0.00';
            $totalTax      = '0.00';
            $taxRate       = (string) $invoice->business->tax_rate;

            foreach ($lines as $line) {
                $result = $this->processLine($return, $invoice, $line, $userId, $taxRate);
                $totalReturned = bcadd($totalReturned, $result['line_total'], 2);
                $totalTax      = bcadd($totalTax, $result['tax'], 2);
            }

            $return->total_returned = $totalReturned;
            $return->status = SalesReturnStatus::Procesada;
            $return->save();

            // UNA nota de crédito por devolución (RF-10-01).
            $creditNote = $this->generarNotaCredito($invoice, $return, $totalReturned, $totalTax, $userId, $cashSessionId);

            // Resarcimiento ramificado por condición de la factura original (RF-10-03).
            $this->resarcir($invoice, $creditNote, $totalReturned, $userId, $cashSessionId);

            return $return->load('items', 'creditNote');
        });
    }

    // ─────── Helpers privados ───────

    /** Valida el devolvible, registra la línea, y ejecuta reingreso o merma. */
    private function processLine(SalesReturn $return, Invoice $invoice, array $line, int $userId, string $taxRate): array
    {
        $saleItem = SaleItem::query()->whereKey($line['sale_item_id'])->lockForUpdate()->firstOrFail();
        $qty      = (string) $line['quantity'];

        if (bccomp($qty, '0', 3) <= 0) {
            throw new DomainException('La cantidad a devolver debe ser mayor que cero.');
        }

        // ERR-10: devolvible = entregado - ya devuelto. NUNCA sobre lo facturado.
        $returnable = bcsub((string) $saleItem->dispatched_quantity, (string) $saleItem->returned_quantity, 3);
        if (bccomp($qty, $returnable, 3) > 0) {
            throw new ReturnQuantityException($returnable, $qty);
        }

        $reason      = ReturnReasonCode::from($line['reason_code']);
        $destination = isset($line['destination'])
            ? ReturnDestination::from($line['destination'])
            : $reason->defaultDestination();

        // Combinación prohibida: vencido/defecto NO reingresan al stock vendible.
        if ($destination === ReturnDestination::Reingreso
            && in_array($reason, [ReturnReasonCode::Vencido, ReturnReasonCode::DefectoFabrica], true)) {
            throw new DomainException("Un producto '{$reason->value}' no puede reingresar al inventario vendible.");
        }

        $warehouseId = $line['warehouse_id'] ?? $this->resolveWarehouse($invoice->branch_id);
        $unitPrice   = (string) $saleItem->unit_price;   // precio congelado de la factura
        $lineTotal   = bcmul($qty, $unitPrice, 2);

        $return->items()->create([
            'product_id'   => $saleItem->product_id,
            'sale_item_id' => $saleItem->id,
            'warehouse_id' => $warehouseId,
            'quantity'     => $qty,
            'unit_price'   => $unitPrice,
            'destination'  => $destination,
            'reason_code'  => $reason,
            // line_total → booted
        ]);

        // Destino físico.
        $this->applyPhysicalDestination($saleItem, $qty, $destination, $warehouseId, $userId);

        // Acumula lo devuelto. chk_sale_item_return_not_exceed (<= dispatched) es la red final.
        $saleItem->returned_quantity = bcadd((string) $saleItem->returned_quantity, $qty, 3);
        $saleItem->save();

        // IVA proporcional revertido desde la figura congelada (solo si el producto era gravable).
        $tax = $saleItem->product->is_taxable ? bcmul($lineTotal, $taxRate, 2) : '0.00';

        return ['line_total' => $lineTotal, 'tax' => $tax];
    }

    /** Reingreso → suma stock + recalcula costo promedio (InventoryService). Merma → pérdidas. */
    private function applyPhysicalDestination(SaleItem $saleItem, string $qty, ReturnDestination $dest, int $warehouseId, int $userId): void
    {
        $product = $saleItem->product;

        // Servicios no tienen existencia física: no reingresan ni mermarían.
        if ($product->type === ProductType::Service || ! $product->tracks_inventory) {
            return;
        }

        if ($dest === ReturnDestination::Reingreso) {
            // RF-10-02: reingreso a COSTO (no precio), recalcula promedio ponderado.
            if ($product->type === ProductType::Simple) {
                $this->inventory->ingresar(
                    productId: $product->id, warehouseId: $warehouseId, quantity: $qty,
                    unitCost: (string) $saleItem->unit_cost, userId: $userId, reason: 'Reingreso por devolución',
                );
            } else {
                // Compuesto: reingresa insumos según el snapshot congelado.
                foreach (($saleItem->recipe_snapshot ?? []) as $l) {
                    $needed = bcmul((string) $l['quantity'], $qty, 3);
                    $this->inventory->ingresar(
                        productId: (int) $l['ingredient_id'], warehouseId: $warehouseId, quantity: $needed,
                        unitCost: '0.0000', userId: $userId, reason: 'Reingreso insumo por devolución',
                    );
                }
            }
            return;
        }

        // Merma: va a pérdidas reutilizando inventory_adjustments tipo 'merma' (nota del .md).
        // NO reingresa al stock vendible; el adjustment documenta la pérdida.
        InventoryAdjustment::create([
            'warehouse_id' => $warehouseId,
            'user_id'      => $userId,
            'type'         => InventoryAdjustmentType::Merma,
            'reason'       => 'Merma por devolución dañada',
            'adjusted_at'  => now(),
        ]);
        // Nota: la merma de un producto devuelto NO sube stock (nunca entró); el adjustment
        // es el registro contable de la pérdida para el balance de la sucursal (RF-10-02).
    }

    /** RF-10-01: una NC por devolución, con folio de document_sequences (tipo credit_note). */
    private function generarNotaCredito(Invoice $invoice, SalesReturn $return, string $total, string $tax, int $userId, ?int $cashSessionId): CreditNote
    {
        $seq = DocumentSequence::query()
            ->where('business_id', $invoice->business_id)
            ->where('document_type', 'credit_note')
            ->lockForUpdate()->firstOrFail();

        $folio = $seq->prefix.str_pad((string) $seq->next_number, 8, '0', STR_PAD_LEFT);
        $seq->next_number += 1;
        $seq->save();

        $note = new CreditNote([
            'invoice_id'      => $invoice->id,
            'sales_return_id' => $return->id,
            'customer_id'     => $invoice->customer_id,
            'cash_session_id' => $cashSessionId,
            'issued_by'       => $userId,
            'resolution_type' => $this->resolveResolutionType($invoice),
            'total_amount'    => $total,
            'tax_amount'      => $tax,
            'issued_at'       => now(),
        ]);
        $note->folio  = $folio;
        $note->status = CreditNoteStatus::Emitida;
        $note->save();

        return $note;
    }

    /**
     * RF-10-03 / ERR-10B: la vía de resarcimiento depende de la factura original.
     *  - Crédito con saldo pendiente → PRIMERO reduce la CxC (no se reembolsa lo no pagado).
     *  - Contado pagado → reembolso en efectivo (requiere caja) o saldo a favor.
     */
    private function resarcir(Invoice $invoice, CreditNote $note, string $amount, int $userId, ?int $cashSessionId): void
    {
        if ($invoice->payment_type === InvoicePaymentType::Credito) {
            $ar = $invoice->accountReceivable;
            $pendingBalance = $ar !== null ? (string) $ar->balance : '0.00';

            if (bccomp($pendingBalance, '0', 2) > 0) {
                // Reduce la deuda hasta donde alcance el monto devuelto (ERR-10B).
                $reduction = bccomp($amount, $pendingBalance, 2) > 0 ? $pendingBalance : $amount;
                $this->receivable->reducirPorNotaCredito($ar, $reduction);

                $remainder = bcsub($amount, $reduction, 2);
                // El excedente (ya pagado) se resarce en efectivo/saldo si queda algo.
                if (bccomp($remainder, '0', 2) > 0) {
                    $this->reembolsarOSaldo($invoice, $note, $remainder, $userId, $cashSessionId);
                }
                return;
            }
        }

        // Contado (o crédito ya pagado): reembolso en efectivo / saldo a favor.
        $this->reembolsarOSaldo($invoice, $note, $amount, $userId, $cashSessionId);
    }

    /** Reembolso en efectivo (egreso de caja, requiere ROL-01 en la capa API) o saldo a favor (NC). */
    private function reembolsarOSaldo(Invoice $invoice, CreditNote $note, string $amount, int $userId, ?int $cashSessionId): void
    {
        if ($note->resolution_type === CreditNoteResolutionType::ReembolsoEfectivo) {
            if ($cashSessionId === null) {
                throw new InvalidRefundMethodException('El reembolso en efectivo requiere una sesión de caja activa.');
            }
            // Egreso de caja vinculado a la NC (RF-10-03). Autorización ROL-01 → Policy (diferida).
            $this->cash->registrarMovimiento(
                sessionId:     $cashSessionId,
                type:          CashMovementType::Egreso,
                category:      CashMovementCategory::EgresoAutorizado,
                paymentMethod: PaymentMethod::Efectivo,
                amount:        $amount,
                userId:        $userId,
                authorizedBy:  $userId,   // el autorizante real lo fija la Policy ROL-01
                description:   'Reembolso NC '.$note->folio,
            );
        }
        // Si es 'nota_credito_saldo': la propia NC queda como saldo a favor (sin columna nueva, Fase 1).
    }

    private function resolveResolutionType(Invoice $invoice): CreditNoteResolutionType
    {
        // Crédito con saldo → reduce CxC; contado → reembolso efectivo por defecto.
        if ($invoice->payment_type === InvoicePaymentType::Credito
            && $invoice->accountReceivable !== null
            && bccomp((string) $invoice->accountReceivable->balance, '0', 2) > 0) {
            return CreditNoteResolutionType::ReduccionCxc;
        }
        return CreditNoteResolutionType::ReembolsoEfectivo;
    }

    private function resolveWarehouse(int $branchId): int
    {
        $w = \App\Models\Warehouse::query()->where('branch_id', $branchId)->where('is_default', true)->first();
        if ($w === null) {
            throw new DomainException('La sucursal no tiene bodega por defecto asignada.');
        }
        return $w->id;
    }
}
