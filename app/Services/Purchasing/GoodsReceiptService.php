<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\AccountPayableStatus;
use App\Enums\GoodsReceiptMatchStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseStateException;
use App\Exceptions\PurchaseMatchException;
use App\Models\AccountPayable;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


// 3-Way Match. Núcleo financiero del módulo.
final class GoodsReceiptService
{
    private const QTY_SCALE = 3;

    private const COST_SCALE = 4;

    private const MONEY_SCALE = 2;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {
    }

    /**
     * @param  array<int, array{purchase_order_item_id: int, received_quantity: string, invoiced_unit_cost: string}>  $lines
     */
    public function recibir(
        User $actor,
        int $purchaseOrderId,
        int $warehouseId,
        array $lines,
        ?string $supplierInvoiceNumber,
        ?string $supplierInvoiceTotal,
        string $tolerance
    ): GoodsReceipt {
        // Se resuelve el veredicto fuera de la transacción de escritura: la
        // evaluación no toca datos.
        $receipt = DB::transaction(function () use (
            $actor, $purchaseOrderId, $warehouseId, $lines,
            $supplierInvoiceNumber, $supplierInvoiceTotal, $tolerance
        ): GoodsReceipt {
            $order = PurchaseOrder::query()
                ->where('business_id', $actor->business_id)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $order->status->canReceive()) {
                throw InvalidPurchaseStateException::orderNotReceivable($order->id);
            }

            // Líneas de orden implicadas, bajo lock (received_quantity se acumula).
            $orderItems = PurchaseOrderItem::query()
                ->where('purchase_order_id', $order->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $evaluation = $this->evaluate($lines, $orderItems, $supplierInvoiceTotal, $tolerance);

            $matchStatus = $evaluation['matched']
                ? GoodsReceiptMatchStatus::Ok
                : GoodsReceiptMatchStatus::Discrepancia;

            // Persistencia de la cabecera. Siempre
            $receipt = new GoodsReceipt();
            $receipt->business_id = $actor->business_id;
            $receipt->purchase_order_id = $order->id;
            $receipt->warehouse_id = $warehouseId;
            $receipt->user_id = $actor->id;
            $receipt->supplier_invoice_number = $supplierInvoiceNumber;
            $receipt->supplier_invoice_total = $supplierInvoiceTotal;
            $receipt->match_status = $matchStatus;
            $receipt->received_at = Carbon::now();
            $receipt->save();

            // Persistencia de items de evidencia. Siempre
            foreach ($evaluation['lines'] as $line) {
                $receipt->items()->create([
                    'purchase_order_item_id' => $line['purchase_order_item_id'],
                    'product_id'             => $line['product_id'],
                    'received_quantity'      => $line['received_quantity'],
                    'invoiced_unit_cost'     => $line['invoiced_unit_cost'],
                    'line_total'             => $line['line_total'],
                    'matched'                => $line['matched'],
                ]);
            }

            // Pendiente si ok, congelada si discrepancia
            $payable = new AccountPayable();
            $payable->business_id = $actor->business_id;
            $payable->supplier_id = $order->supplier_id;
            $payable->purchase_order_id = $order->id;
            $payable->goods_receipt_id = $receipt->id;
            $payable->total_amount = $evaluation['computed_total'];
            $payable->paid_amount = '0.00';
            $payable->status = $matchStatus->allowsInventoryEntry()
                ? AccountPayableStatus::Pendiente
                : AccountPayableStatus::Congelada;
            $payable->save();

            // Solo si ok: ingreso a inventario + acumulación de recibido
            if ($matchStatus->allowsInventoryEntry()) {
                $this->applyInventoryEntry($actor, $order, $receipt->warehouse_id, $evaluation['lines'], $orderItems);
                $this->refreshOrderStatus($order, $orderItems);
            }

            return $receipt->refresh()->load(['items', 'accountPayable']);
        });

        // La señal 409 se emite DESPUÉS del commit. La evidencia persiste.
        if ($receipt->match_status === GoodsReceiptMatchStatus::Discrepancia) {
            throw new PurchaseMatchException($receipt);
        }

        return $receipt;
    }

    public function resolver(User $actor, GoodsReceipt $receipt, string $resolution, ?string $notes): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $receipt, $resolution, $notes): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->whereKey($receipt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $receipt->match_status->isResolvable()) {
                throw InvalidPurchaseStateException::receiptNotResolvable($receipt->id);
            }

            $order = PurchaseOrder::query()->whereKey($receipt->purchase_order_id)->lockForUpdate()->firstOrFail();

            $payable = AccountPayable::query()
                ->where('goods_receipt_id', $receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($resolution === 'aceptar') {
                // Reconstruye el ingreso desde la evidencia inmutable (H-39).
                $orderItems = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $order->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lines = $receipt->items->map(static fn ($item): array => [
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                    'product_id'             => $item->product_id,
                    'received_quantity'      => (string) $item->received_quantity,
                    'invoiced_unit_cost'     => (string) $item->invoiced_unit_cost,
                ])->all();

                $this->applyInventoryEntry($actor, $order, $receipt->warehouse_id, $lines, $orderItems);
                $this->refreshOrderStatus($order, $orderItems);

                $receipt->match_status = GoodsReceiptMatchStatus::Ok;
                $receipt->notes = $notes;
                $receipt->save();

                $payable->status = AccountPayableStatus::Pendiente;
                $payable->unblocked_by = $actor->id;
                $payable->save();
            } else {
                // rechazar: terminal. Ni inventario ni descongelamiento.
                $receipt->match_status = GoodsReceiptMatchStatus::Bloqueada;
                $receipt->notes = $notes;
                $receipt->save();
                // La CxP permanece congelada (terminal).
            }

            return $receipt->refresh()->load(['items', 'accountPayable']);
        });
    }

    /**
     * @param  array<int, array{purchase_order_item_id: int, received_quantity: string, invoiced_unit_cost: string}>  $lines
     * @param  \Illuminate\Support\Collection<int, PurchaseOrderItem>  $orderItems
     * @return array{matched: bool, computed_total: string, lines: array<int, array{purchase_order_item_id: int, product_id: int, received_quantity: string, invoiced_unit_cost: string, line_total: string, matched: bool}>}
     */
    private function evaluate(array $lines, $orderItems, ?string $invoiceTotal, string $tolerance): array
    {
        $evaluatedLines = [];
        $computedTotal = '0.00';
        $allMatched = true;

        foreach ($lines as $line) {
            $orderItem = $orderItems->get($line['purchase_order_item_id']);

            // Línea de recepción que no pertenece a la orden: no coincide.
            if ($orderItem === null) {
                $evaluatedLines[] = [
                    'purchase_order_item_id' => $line['purchase_order_item_id'],
                    'product_id'             => 0,
                    'received_quantity'      => $line['received_quantity'],
                    'invoiced_unit_cost'     => $line['invoiced_unit_cost'],
                    'line_total'             => '0.00',
                    'matched'                => false,
                ];
                $allMatched = false;

                continue;
            }

            $lineTotal = bcmul($line['received_quantity'], $line['invoiced_unit_cost'], self::MONEY_SCALE);
            $computedTotal = bcadd($computedTotal, $lineTotal, self::MONEY_SCALE);

            // (a) costo facturado ⇄ pactado, con tolerancia (escala 4).
            $costDiff = $this->absolute(
                bcsub($line['invoiced_unit_cost'], (string) $orderItem->agreed_unit_cost, self::COST_SCALE)
            );
            $costMatches = bccomp($costDiff, $tolerance, self::COST_SCALE) <= 0;

            // (b) recibido acumulado ⇄ ordenado (escala 3): el acumulado tras
            // esta entrega no debe exceder lo ordenado.
            $accumulated = bcadd((string) $orderItem->received_quantity, $line['received_quantity'], self::QTY_SCALE);
            $qtyMatches = bccomp($accumulated, (string) $orderItem->ordered_quantity, self::QTY_SCALE) <= 0;

            $lineMatched = $costMatches && $qtyMatches;
            $allMatched = $allMatched && $lineMatched;

            $evaluatedLines[] = [
                'purchase_order_item_id' => $line['purchase_order_item_id'],
                'product_id'             => (int) $orderItem->product_id,
                'received_quantity'      => $line['received_quantity'],
                'invoiced_unit_cost'     => $line['invoiced_unit_cost'],
                'line_total'             => $lineTotal,
                'matched'                => $lineMatched,
            ];
        }

        // (c) total declarado ⇄ suma calculada (escala 2, con tolerancia).
        if ($invoiceTotal !== null) {
            $totalDiff = $this->absolute(bcsub($invoiceTotal, $computedTotal, self::MONEY_SCALE));
            $toleranceMoney = bcadd($tolerance, '0', self::MONEY_SCALE);

            if (bccomp($totalDiff, $toleranceMoney, self::MONEY_SCALE) > 0) {
                $allMatched = false;
            }
        }

        return [
            'matched'        => $allMatched,
            'computed_total' => $computedTotal,
            'lines'          => $evaluatedLines,
        ];
    }

    /**
     * @param  array<int, array{purchase_order_item_id: int, product_id: int, received_quantity: string, invoiced_unit_cost: string}>  $lines
     * @param  \Illuminate\Support\Collection<int, PurchaseOrderItem>  $orderItems
     */
    private function applyInventoryEntry(User $actor, PurchaseOrder $order, int $warehouseId, array $lines, $orderItems): void
    {
        foreach ($lines as $line) {
            $this->inventory->ingresarPorCompra(
                actor: $actor,
                warehouseId: $warehouseId,
                productId: $line['product_id'],
                quantity: $line['received_quantity'],
                unitCost: $line['invoiced_unit_cost'],
                purchaseOrderId: $order->id,
            );

            // Acumula lo recibido en la línea de orden (D-14).
            $orderItem = $orderItems->get($line['purchase_order_item_id']);
            if ($orderItem !== null) {
                $orderItem->received_quantity = bcadd(
                    (string) $orderItem->received_quantity,
                    $line['received_quantity'],
                    self::QTY_SCALE
                );
                $orderItem->save();
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PurchaseOrderItem>  $orderItems
     */
    private function refreshOrderStatus(PurchaseOrder $order, $orderItems): void
    {
        $fresh = $orderItems->fresh();
        $allComplete = $fresh->every(static fn (PurchaseOrderItem $item): bool => $item->isFullyReceived());

        $order->status = $allComplete ? PurchaseOrderStatus::Recibida : PurchaseOrderStatus::Parcial;
        $order->save();
    }

    private function absolute(string $value): string
    {
        return $value[0] === '-' ? substr($value, 1) : $value;
    }
}
