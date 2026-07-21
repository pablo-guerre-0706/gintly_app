<?php

namespace App\Services\Purchase;

use App\Enums\AccountPayableStatus;
use App\Enums\GoodsReceiptMatchStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseMatchException;
use App\Exceptions\SupplierNotApprovedException;
use App\Models\AccountPayable;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseService
{
    // Inyección por constructor: el estándar único de entrada de mercancía (MOD-03).
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * Crea una orden de compra en 'borrador'. Bloquea si el proveedor no está aprobado (ERR-04B).
     * line_total lo calcula el modelo (booted); expected_total lo recalcula el servicio.
     *
     * @throws SupplierNotApprovedException
     */
    public function crearOrden(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $this->assertSupplierApproved($supplier);

            $po = PurchaseOrder::create([
                'branch_id'      => $data['branch_id'],
                'supplier_id'    => $supplier->id,
                'user_id'        => $data['user_id'],
                'code'           => $this->generateOrderCode(),
                'status'         => PurchaseOrderStatus::Borrador,
                'expected_total' => '0.00',
                'ordered_at'     => $data['ordered_at'],
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $po->items()->create([
                    'product_id'        => $line['product_id'],
                    'ordered_quantity'  => $line['ordered_quantity'],
                    'received_quantity' => '0.000',
                    'agreed_unit_cost'  => $line['agreed_unit_cost'],
                    // line_total → lo escribe el saving() de PurchaseOrderItem
                ]);
            }

            $this->recalcularExpectedTotal($po);

            return $po->refresh()->load('items');
        });
    }

    /**
     * Emite la orden (borrador → emitida). Revalida la aprobación del proveedor:
     * pudo perderla entre la creación y la emisión.
     *
     * @throws SupplierNotApprovedException
     */
    public function emitirOrden(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrderStatus::Borrador) {
            throw new DomainException('Solo una orden en borrador puede emitirse.');
        }

        $this->assertSupplierApproved($po->supplier);

        $po->status = PurchaseOrderStatus::Emitida;
        $po->save();

        return $po;
    }

    /**
     * Recepción con 3-Way Match. Si todo cuadra: ingresa a inventario (costo promedio
     * ponderado), acumula received_quantity y genera CxP 'pendiente'. Si hay discrepancia:
     * NO ingresa a inventario, NO acumula, congela la CxP y señala 409 (ERR-04).
     *
     * @throws PurchaseMatchException
     */
    public function recibir(PurchaseOrder $po, array $data): GoodsReceipt
    {
        if (! $po->status->canReceive()) {
            throw new DomainException('La orden no admite recepción en su estado actual.');
        }

        // 1) Match como operación PURA (solo lectura): decide el resultado antes de escribir.
        $match = $this->performThreeWayMatch($po, $data);

        // 2) Persistencia atómica del recibo + líneas + CxP según el resultado.
        $receipt = DB::transaction(function () use ($po, $data, $match) {
            $receipt = GoodsReceipt::create([
                'purchase_order_id'       => $po->id,
                'warehouse_id'            => $data['warehouse_id'],
                'user_id'                 => $data['user_id'],
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_invoice_total'  => $data['supplier_invoice_total'] ?? $match['computed_total'],
                'match_status'            => $match['matched']
                    ? GoodsReceiptMatchStatus::Ok
                    : GoodsReceiptMatchStatus::Discrepancia,
                'received_at'             => now(),
                'notes'                   => $data['notes'] ?? null,
            ]);

            // Las líneas SIEMPRE se persisten: son la evidencia de la recepción (haya o no discrepancia).
            foreach ($match['lines'] as $line) {
                $receipt->items()->create([
                    'purchase_order_item_id' => $line['purchase_order_item_id'],
                    'product_id'             => $line['product_id'],
                    'received_quantity'      => $line['received_quantity'],
                    'invoiced_unit_cost'     => $line['invoiced_unit_cost'],
                    'line_total'             => $line['line_total'],
                    'matched'                => $line['matched'],
                ]);
            }

            if ($match['matched']) {
                $this->applyReceiptToInventory($receipt, $match['lines']);       // entra a stock + kardex
                $this->generateOrUpdatePayable($receipt, AccountPayableStatus::Pendiente);
                $this->refreshPurchaseOrderStatus($po);                          // parcial / recibida
            } else {
                // Discrepancia: la CxP nace CONGELADA. Ni un gramo entra a inventario.
                $this->generateOrUpdatePayable($receipt, AccountPayableStatus::Congelada);

                $this->anomalies->registrarSilencioso(
                    businessId: $receipt->business_id,
                    code: \App\Enums\AnomalyRuleCode::Discrepancia3Way,
                    sourceType: 'goods_receipt',
                    sourceId: $receipt->id,
                    expected: (string) $po->expected_total,
                    actual: (string) $match['computed_total'],
                );
            }

            return $receipt;
        });

        // 3) "Throw después del commit": el recibo congelado ya persistió para ROL-01;
        //    aquí solo señalamos el 409 al cliente. El throw NO revierte nada.
        if (! $match['matched']) {
            throw new PurchaseMatchException;
        }

        return $receipt->load('items');
    }

    /**
     * Resolución de discrepancia por ROL-01 (la autoridad se valida en la Policy, diferida).
     * 'aceptar' libera el ingreso a inventario y descongela la CxP usando las líneas persistidas.
     * 'rechazar' deja el recibo bloqueado y la CxP congelada (estado terminal, no se paga).
     */
    public function resolverDiscrepancia(GoodsReceipt $receipt, string $resolution, int $resolverUserId): GoodsReceipt
    {
        if ($receipt->match_status !== GoodsReceiptMatchStatus::Discrepancia) {
            throw new DomainException('El recibo no está en estado de discrepancia.');
        }

        return DB::transaction(function () use ($receipt, $resolution, $resolverUserId) {
            $payable = AccountPayable::query()->where('goods_receipt_id', $receipt->id)->firstOrFail();

            if ($resolution === 'aceptar') {
                // Reconstruimos las líneas desde la evidencia persistida (goods_receipt_items).
                $lines = $receipt->items->map(fn (\App\Models\GoodsReceiptItem $i): array => [
                    'purchase_order_item_id' => $i->purchase_order_item_id,
                    'product_id'             => $i->product_id,
                    'received_quantity'      => (string) $i->received_quantity,
                    'invoiced_unit_cost'     => (string) $i->invoiced_unit_cost,
                ])->all();

                $this->applyReceiptToInventory($receipt, $lines);
                $this->refreshPurchaseOrderStatus($receipt->purchaseOrder);

                $receipt->match_status = GoodsReceiptMatchStatus::Ok;
                $payable->status       = AccountPayableStatus::Pendiente;
                $payable->unblocked_by = $resolverUserId;   // trazabilidad del desbloqueo (RF-04-04)
            } else { // 'rechazar'
                $receipt->match_status = GoodsReceiptMatchStatus::Bloqueada;
                // La CxP permanece 'congelada' (terminal): mercancía rechazada, deuda no exigible.
            }

            $receipt->save();
            $payable->save();

            return $receipt->load('items');
        });
    }

    // ─────── Helpers privados ───────

    /** RF-04-02 / ERR-04B: sin estado 'aprobado', no hay orden de compra. */
    private function assertSupplierApproved(Supplier $supplier): void
    {
        if (! $supplier->status->canReceiveOrders()) {
            throw new SupplierNotApprovedException;
        }
    }

    /**
     * 3-Way Match puro: cruza recibido ⇄ ordenado ⇄ facturado, y costo facturado ⇄ pactado.
     * Tolerancia 0.00 por defecto (regla de anomalía 'discrepancia_3way' es estricta).
     * Devuelve el veredicto sin tocar la BD.
     */
    private function performThreeWayMatch(PurchaseOrder $po, array $data): array
    {
        $tolerance     = (string) ($data['tolerance'] ?? '0.00');
        $poItems       = $po->items()->get()->keyBy('id');
        $lines         = [];
        $matched       = true;
        $computedTotal = '0.00';

        foreach ($data['lines'] as $l) {
            /** @var PurchaseOrderItem|null $poItem */
            $poItem = $poItems->get($l['purchase_order_item_id']);

            // Línea que no pertenece a la OC → discrepancia dura, no la evaluamos más.
            if ($poItem === null) {
                $matched = false;
                $lines[] = [
                    'purchase_order_item_id' => $l['purchase_order_item_id'],
                    'product_id'             => $l['product_id'] ?? null,
                    'received_quantity'      => (string) $l['received_quantity'],
                    'invoiced_unit_cost'     => (string) $l['invoiced_unit_cost'],
                    'line_total'             => bcmul((string) $l['received_quantity'], (string) $l['invoiced_unit_cost'], 2),
                    'matched'                => false,
                ];
                continue;
            }

            $received     = (string) $l['received_quantity'];
            $invoicedCost = (string) $l['invoiced_unit_cost'];
            $agreedCost   = (string) $poItem->agreed_unit_cost;
            $lineTotal    = bcmul($received, $invoicedCost, 2);
            $computedTotal = bcadd($computedTotal, $lineTotal, 2);

            // (a) Costo: facturado ⇄ pactado (dentro de tolerancia).
            $costOk = bccomp($this->abs(bcsub($invoicedCost, $agreedCost, 4)), $tolerance, 4) <= 0;

            // (b) Cantidad: acumulado (lo ya recibido + este) ⇄ ordenado. Exceso = discrepancia.
            $cumulative = bcadd((string) $poItem->received_quantity, $received, 3);
            $qtyOk      = bccomp($cumulative, (string) $poItem->ordered_quantity, 3) <= 0;

            $lineMatched = $costOk && $qtyOk;
            $matched     = $matched && $lineMatched;

            $lines[] = [
                'purchase_order_item_id' => $poItem->id,
                'product_id'             => $poItem->product_id,
                'received_quantity'      => $received,
                'invoiced_unit_cost'     => $invoicedCost,
                'line_total'             => $lineTotal,
                'matched'                => $lineMatched,
            ];
        }

        // (c) Total de factura declarado ⇄ suma calculada.
        if (isset($data['supplier_invoice_total'])) {
            $invoiceDiff = $this->abs(bcsub((string) $data['supplier_invoice_total'], $computedTotal, 2));
            if (bccomp($invoiceDiff, $tolerance, 2) > 0) {
                $matched = false;
            }
        }

        return ['matched' => $matched, 'lines' => $lines, 'computed_total' => $computedTotal];
    }

    /** Ingresa cada línea a inventario (estándar único MOD-03) y acumula received_quantity. */
    private function applyReceiptToInventory(GoodsReceipt $receipt, array $lines): void
    {
        foreach ($lines as $line) {
            // ingresar() abre su propia transacción → savepoint dentro de la nuestra. Atómico.
            $this->inventory->ingresar(
                productId:       $line['product_id'],
                warehouseId:     $receipt->warehouse_id,
                quantity:        $line['received_quantity'],
                unitCost:        $line['invoiced_unit_cost'],
                userId:          $receipt->user_id,
                purchaseOrderId: $receipt->purchase_order_id,
                reason:          'Recepción de compra',
            );

            $poItem = PurchaseOrderItem::query()->findOrFail($line['purchase_order_item_id']);
            $poItem->received_quantity = bcadd((string) $poItem->received_quantity, $line['received_quantity'], 3);
            $poItem->save();
        }
    }

    /** RF-04-01: cada recepción genera su CxP. Una CxP por recibo (con o sin congelamiento). */
    private function generateOrUpdatePayable(GoodsReceipt $receipt, AccountPayableStatus $status): AccountPayable
    {
        $po = $receipt->purchaseOrder;

        return AccountPayable::create([
            'supplier_id'       => $po->supplier_id,
            'purchase_order_id' => $po->id,
            'goods_receipt_id'  => $receipt->id,
            'total_amount'      => (string) $receipt->supplier_invoice_total,
            'paid_amount'       => '0.00',
            'status'            => $status,
            'due_date'          => null,
        ]);
    }

    /** Deriva el estado de la OC: recibida (todo) / parcial (algo) según lo acumulado. */
    private function refreshPurchaseOrderStatus(PurchaseOrder $po): void
    {
        $po->load('items');
        $allComplete = true;
        $anyReceived = false;

        foreach ($po->items as $item) {
            if (bccomp((string) $item->received_quantity, '0', 3) > 0) {
                $anyReceived = true;
            }
            if (bccomp((string) $item->received_quantity, (string) $item->ordered_quantity, 3) < 0) {
                $allComplete = false;
            }
        }

        if ($allComplete) {
            $po->status = PurchaseOrderStatus::Recibida;
        } elseif ($anyReceived) {
            $po->status = PurchaseOrderStatus::Parcial;
        }

        $po->save();
    }

    private function recalcularExpectedTotal(PurchaseOrder $po): void
    {
        $total = '0.00';
        foreach ($po->items()->get() as $item) {
            $total = bcadd($total, (string) $item->line_total, 2);
        }
        $po->expected_total = $total;
        $po->save();
    }

    private function generateOrderCode(): string
    {
        // MVP: fecha + sufijo aleatorio; el unique(business_id, code) es la red de seguridad.
        return 'OC-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
    }

    private function abs(string $decimal): string
    {
        return bccomp($decimal, '0', 6) < 0 ? bcmul($decimal, '-1', 6) : $decimal;
    }
}
