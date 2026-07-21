<?php

namespace App\Services\Dispatch;

use App\Enums\DispatchStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProductType;
use App\Exceptions\DispatchExceedsBalanceException;
use App\Exceptions\DispatchOnVoidedInvoiceException;
use App\Models\Dispatch;
use App\Models\Invoice;
use App\Models\SaleItem;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * Registra un retiro (RF-09-01): total o parcial. Por cada línea:
     *  - valida saldo pendiente (ERR-09) bajo lock,
     *  - descuenta físico real vía InventoryService::retirar() (consume reserva + kardex),
     *  - sube dispatched_quantity (el CHECK de motor es la red anti-sobre-retiro).
     * Todo atómico. Bloquea si la factura está anulada (ERR-09B).
     *
     * @throws DispatchExceedsBalanceException|DispatchOnVoidedInvoiceException
     */
    public function registrarRetiro(int $invoiceId, array $lines, int $userId, ?string $receivedBy = null, ?string $notes = null): Dispatch
    {
        return DB::transaction(function () use ($invoiceId, $lines, $userId, $receivedBy, $notes) {
            // Bloqueo de la factura: leemos su estado bajo lock (ERR-09B).
            $invoice = Invoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            if ($invoice->status === InvoiceStatus::Anulada) {
                throw new DispatchOnVoidedInvoiceException;
            }

            $warehouseId = $this->resolveWarehouse($invoice->branch_id);

            $dispatch = Dispatch::create([
                'branch_id'     => $invoice->branch_id,
                'invoice_id'    => $invoice->id,
                'warehouse_id'  => $warehouseId,
                'user_id'       => $userId,
                'code'          => 'RET-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
                'received_by'   => $receivedBy,
                'dispatched_at' => now(),
                // status → 'registrado'
            ]);

            foreach ($lines as $line) {
                $this->processLine($dispatch, (int) $line['sale_item_id'], (string) $line['quantity'], $warehouseId, $userId);
            }

            return $dispatch->load('items');
        });
    }

    /**
     * Reversión de un retiro registrado por error (RF-09-04): potestad ROL-02 (Policy diferida).
     * Reingresa el stock a la bodega de origen, RE-RESERVA (la mercancía vuelve a estar
     * comprometida por la factura viva) y baja dispatched_quantity. Marca 'revertido' (no borra).
     */
    public function revertir(Dispatch $dispatch, int $reverterId, string $reason): Dispatch
    {
        if ($dispatch->status !== DispatchStatus::Registrado) {
            throw new DomainException('Solo un retiro registrado puede revertirse.');
        }

        return DB::transaction(function () use ($dispatch, $reverterId, $reason) {
            foreach ($dispatch->items()->with('saleItem.product')->get() as $item) {
                $saleItem = SaleItem::query()->whereKey($item->sale_item_id)->lockForUpdate()->firstOrFail();
                $product  = $saleItem->product;

                if ($product->type !== ProductType::Service && $product->tracks_inventory) {
                    // Reingresa lo que había salido (a costo congelado del ítem) → entrada en kardex.
                    $this->reingresarSegunNaturaleza($item, $saleItem, $dispatch->warehouse_id, $dispatch->user_id);
                    // Re-reserva: la factura sigue viva, la mercancía vuelve a estar comprometida.
                    $this->rereservarSegunNaturaleza($item, $saleItem, $dispatch->warehouse_id, $dispatch->user_id);
                }

                // Baja el acumulado despachado (el CHECK >= 0 protege contra doble reversión).
                $saleItem->dispatched_quantity = bcsub((string) $saleItem->dispatched_quantity, (string) $item->quantity, 3);
                $saleItem->save();
            }

            $dispatch->status      = DispatchStatus::Revertido;
            $dispatch->reverted_by = $reverterId;
            $dispatch->reverted_at = now();
            $dispatch->revert_reason = $reason;
            $dispatch->save();

            return $dispatch->load('items');
        });
    }

    // ─────────────────────────── Helpers privados ───────────────────────────

    /** Procesa una línea de retiro: valida saldo, descuenta físico, acumula despachado. */
    private function processLine(Dispatch $dispatch, int $saleItemId, string $quantity, int $warehouseId, int $userId): void
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException('La cantidad a retirar debe ser mayor que cero.');
        }

        // lockForUpdate sobre la línea de venta: serializa retiros concurrentes de la misma línea.
        $saleItem = SaleItem::query()->whereKey($saleItemId)->lockForUpdate()->firstOrFail();
        $product  = $saleItem->product;

        // Servicios no se retiran ni descuentan inventario (RF-09-03).
        if ($product->type === ProductType::Service) {
            throw new DomainException('Un servicio no genera retiro de mercancía.');
        }

        // Saldo pendiente de ESTA línea (RF-09-02).
        $pending = bcsub((string) $saleItem->quantity, (string) $saleItem->dispatched_quantity, 3);
        if (bccomp($quantity, $pending, 3) > 0) {
            throw new DispatchExceedsBalanceException($pending, $quantity);   // ERR-09
        }

        // Descuento físico real (solo si controla inventario).
        if ($product->tracks_inventory) {
            $this->retirarSegunNaturaleza($dispatch, $saleItem, $quantity, $warehouseId, $userId);
        }

        // Registra la línea del despacho (evidencia).
        $dispatch->items()->create([
            'sale_item_id' => $saleItem->id,
            'product_id'   => $product->id,
            'quantity'     => $quantity,
        ]);

        // Acumula lo despachado. chk_sale_item_dispatch_not_exceed es la red final.
        $saleItem->dispatched_quantity = bcadd((string) $saleItem->dispatched_quantity, $quantity, 3);
        $saleItem->save();
    }

    /** Simple: retira el propio producto. Compuesto: retira cada insumo del snapshot × cantidad. */
    private function retirarSegunNaturaleza(Dispatch $dispatch, SaleItem $saleItem, string $quantity, int $warehouseId, int $userId): void
    {
        if ($saleItem->product->type === ProductType::Simple) {
            $this->inventory->retirar(
                productId:   $saleItem->product_id,
                warehouseId: $warehouseId,
                quantity:    $quantity,
                userId:      $userId,
                dispatchId:  $dispatch->id,
                reason:      'Retiro de mercancía',
            );
            return;
        }

        // Compuesto: descuenta insumos según el recipe_snapshot congelado (coherente con la reserva).
        foreach (($saleItem->recipe_snapshot ?? []) as $line) {
            $needed = bcmul((string) $line['quantity'], $quantity, 3);
            $this->inventory->retirar(
                productId:   (int) $line['ingredient_id'],
                warehouseId: $warehouseId,
                quantity:    $needed,
                userId:      $userId,
                dispatchId:  $dispatch->id,
                reason:      'Retiro de insumo (compuesto)',
            );
        }
    }

    private function reingresarSegunNaturaleza(\App\Models\DispatchItem $item, SaleItem $saleItem, int $warehouseId, int $userId): void
    {
        if ($saleItem->product->type === ProductType::Simple) {
            $this->inventory->ingresar(
                productId: $saleItem->product_id, warehouseId: $warehouseId,
                quantity: (string) $item->quantity, unitCost: (string) $saleItem->unit_cost,
                userId: $userId, reason: 'Reversión de retiro',
            );
            return;
        }
        foreach (($saleItem->recipe_snapshot ?? []) as $line) {
            $needed = bcmul((string) $line['quantity'], (string) $item->quantity, 3);
            $this->inventory->ingresar(
                productId: (int) $line['ingredient_id'], warehouseId: $warehouseId,
                quantity: $needed, unitCost: '0.0000',  // insumo reingresa a su costo promedio vigente
                userId: $userId, reason: 'Reversión de retiro (insumo)',
            );
        }
    }

    private function rereservarSegunNaturaleza(\App\Models\DispatchItem $item, SaleItem $saleItem, int $warehouseId, int $userId): void
    {
        if ($saleItem->product->type === ProductType::Simple) {
            $this->inventory->reservar($saleItem->product_id, $warehouseId, (string) $item->quantity, $userId);
            return;
        }
        foreach (($saleItem->recipe_snapshot ?? []) as $line) {
            $needed = bcmul((string) $line['quantity'], (string) $item->quantity, 3);
            $this->inventory->reservar((int) $line['ingredient_id'], $warehouseId, $needed, $userId);
        }
    }

    /** El retiro sale de la bodega POR DEFECTO de la sucursal (candado default_lock, MOD-03). */
    private function resolveWarehouse(int $branchId): int
    {
        $warehouse = \App\Models\Warehouse::query()
            ->where('branch_id', $branchId)->where('is_default', true)->first();

        if ($warehouse === null) {
            throw new DomainException('La sucursal no tiene bodega por defecto asignada.');
        }
        return $warehouse->id;
    }
}
