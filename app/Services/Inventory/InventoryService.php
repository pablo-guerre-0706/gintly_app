<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventorySyncException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventoryService
{
    /**
     * Reserva stock al facturar (MOD-07). Sube reserved_quantity; sin toca quantity;
     * No escribe kardex. Falla si el disponible (quantity - reserved) no alcanza.
     *
     * @throws InsufficientStockException
     */
    public function reservar(int $productId, int $warehouseId, string $quantity, ?int $userId = null): StockLevel
    {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity) {
            $level = $this->lockLevel($productId, $warehouseId);

            // Disponible = físico - reservado. bccomp compara strings decimales sin error de float.
            $available = bcsub((string) $level->quantity, (string) $level->reserved_quantity, 3);

            if (bccomp($available, $quantity, 3) < 0) {
                throw new InsufficientStockException(
                    $this->sku($productId), $this->warehouseName($warehouseId)
                );
            }

            $level->reserved_quantity = bcadd((string) $level->reserved_quantity, $quantity, 3);
            $level->save();

            return $level;
        });
    }

    /**
     * Libera una reserva (anulación de factura / venta no concretada).
     * Baja reserved_quantity sin tocar quantity ni kardex. Nunca deja reserved < 0.
     */
    public function liberarReserva(int $productId, int $warehouseId, string $quantity): StockLevel
    {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity) {
            $level = $this->lockLevel($productId, $warehouseId);

            $nuevoReservado = bcsub((string) $level->reserved_quantity, $quantity, 3);
            // Piso de seguridad: una doble liberación no debe producir negativos.
            if (bccomp($nuevoReservado, '0', 3) < 0) {
                $nuevoReservado = '0.000';
            }

            $level->reserved_quantity = $nuevoReservado;
            $level->save();

            return $level;
        });
    }

    /**
     * Retiro físico (MOD-09). Baja quantity Y reserved_quantity, y escribe un
     * movimiento 'salida' en el kardex — todo atómico. Es el unico descuento real.
     *
     * @throws InsufficientStockException|InventorySyncException
     */
    public function retirar(
        int $productId,
        int $warehouseId,
        string $quantity,
        ?int $userId = null,
        ?int $dispatchId = null,
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $userId, $dispatchId, $reason) {
            $level = $this->lockLevel($productId, $warehouseId);

            // No se puede retirar más de lo que existe físicamente.
            if (bccomp((string) $level->quantity, $quantity, 3) < 0) {
                throw new InsufficientStockException(
                    $this->sku($productId), $this->warehouseName($warehouseId)
                );
            }

            $level->quantity = bcsub((string) $level->quantity, $quantity, 3);
            // El retiro consume la reserva asociada; con piso en 0 por robustez.
            $nuevoReservado = bcsub((string) $level->reserved_quantity, $quantity, 3);
            $level->reserved_quantity = bccomp($nuevoReservado, '0', 3) < 0 ? '0.000' : $nuevoReservado;
            $level->save();

            return $this->postMovement(
                productId:   $productId,
                warehouseId: $warehouseId,
                type:        InventoryMovementType::Salida,
                quantity:    $quantity,
                balanceAfter: (string) $level->quantity,
                userId:      $userId,
                dispatchId:  $dispatchId,
                reason:      $reason,
            );
        });
    }

    /**
     * Entrada de mercancía (recepción de compra MOD-04, o reingreso por devolución MOD-10).
     * Sube quantity, recalcula costo promedio ponderado, escribe 'entrada' en kardex.
     */
    public function ingresar(
        int $productId,
        int $warehouseId,
        string $quantity,
        string $unitCost,
        ?int $userId = null,
        ?int $purchaseOrderId = null,
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $unitCost, $userId, $purchaseOrderId, $reason) {
            $level = $this->lockLevel($productId, $warehouseId, createIfMissing: true);

            // Costo promedio ponderado: (qtyActual*costoActual + qtyNueva*costoNuevo) / (qtyActual+qtyNueva)
            $valorActual = bcmul((string) $level->quantity, (string) $level->average_cost, 6);
            $valorNuevo  = bcmul($quantity, $unitCost, 6);
            $nuevaQty    = bcadd((string) $level->quantity, $quantity, 3);

            if (bccomp($nuevaQty, '0', 3) > 0) {
                $level->average_cost = bcdiv(bcadd($valorActual, $valorNuevo, 6), $nuevaQty, 4);
            }
            $level->quantity = $nuevaQty;
            $level->save();

            return $this->postMovement(
                productId:    $productId,
                warehouseId:  $warehouseId,
                type:         InventoryMovementType::Entrada,
                quantity:     $quantity,
                balanceAfter: (string) $level->quantity,
                unitCost:     $unitCost,
                userId:       $userId,
                purchaseOrderId: $purchaseOrderId,
                reason:       $reason,
            );
        });
    }

    /**
     * Traspaso entre bodegas: una salida en origen + una entrada en destino, atómico.
     * Bloquea ambas filas en orden determinista (menor product/warehouse primero)
     * para prevenir deadlocks entre traspasos cruzados.
     */
    public function traspasar(StockTransfer $transfer, int $productId, string $quantity): void
    {
        DB::transaction(function () use ($transfer, $productId, $quantity) {
            $origen  = $this->lockLevel($productId, $transfer->from_warehouse_id);

            if (bccomp((string) $origen->quantity, $quantity, 3) < 0) {
                throw new InsufficientStockException(
                    $this->sku($productId), $this->warehouseName($transfer->from_warehouse_id)
                );
            }

            $destino = $this->lockLevel($productId, $transfer->to_warehouse_id, createIfMissing: true);

            // Salida en origen
            $origen->quantity = bcsub((string) $origen->quantity, $quantity, 3);
            $origen->save();
            $this->postMovement(
                productId: $productId, warehouseId: $transfer->from_warehouse_id,
                type: InventoryMovementType::Traspaso, quantity: $quantity,
                balanceAfter: (string) $origen->quantity, userId: $transfer->user_id,
                stockTransferId: $transfer->id, reason: 'Traspaso salida',
            );

            // Entrada en destino (hereda el costo de origen para no distorsionar el promedio)
            $destino->quantity = bcadd((string) $destino->quantity, $quantity, 3);
            $destino->save();
            $this->postMovement(
                productId: $productId, warehouseId: $transfer->to_warehouse_id,
                type: InventoryMovementType::Traspaso, quantity: $quantity,
                balanceAfter: (string) $destino->quantity, userId: $transfer->user_id,
                stockTransferId: $transfer->id, reason: 'Traspaso entrada',
            );
        });
    }

    /**
     * Ajuste por conteo físico (RF-03-03). Lleva el saldo del sistema al valor contado
     * y escribe un movimiento 'ajuste' por la diferencia. adjustmentId enlaza la causa.
     */
    public function ajustarPorConteo(
        int $productId,
        int $warehouseId,
        string $countedQuantity,
        int $adjustmentId,
        ?int $userId = null,
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($productId, $warehouseId, $countedQuantity, $adjustmentId, $userId, $reason) {
            $level = $this->lockLevel($productId, $warehouseId, createIfMissing: true);

            $diferencia = bcsub($countedQuantity, (string) $level->quantity, 3);  // + sobrante / - faltante
            $level->quantity = $countedQuantity;   // el conteo físico es la verdad (BR-03)
            $level->save();

            return $this->postMovement(
                productId:    $productId,
                warehouseId:  $warehouseId,
                type:         InventoryMovementType::Ajuste,
                quantity:     $this->abs($diferencia),
                balanceAfter: (string) $level->quantity,
                userId:       $userId,
                adjustmentId: $adjustmentId,
                reason:       $reason ?? 'Ajuste por conteo físico',
            );
        });
    }

    // ─────── Helpers privados ───────

    /**
     * Bloquea (o crea) la fila de saldo. lockForUpdate() dentro de la transacción
     * serializa el acceso concurrente a esta fila: aquí muere la sobreventa (RF-03-05).
     */
    private function lockLevel(int $productId, int $warehouseId, bool $createIfMissing = false): StockLevel
    {
        $query = StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate();

        $level = $query->first();

        if ($level === null) {
            if (! $createIfMissing) {
                throw new InsufficientStockException(
                    $this->sku($productId), $this->warehouseName($warehouseId)
                );
            }
            // firstOrCreate no respeta lockForUpdate; creamos explícito y el UNIQUE(product,warehouse) protege.
            $level = StockLevel::create([
                'product_id'        => $productId,
                'warehouse_id'      => $warehouseId,
                'quantity'          => '0.000',
                'reserved_quantity' => '0.000',
                'average_cost'      => '0.0000',
            ]);
        }

        return $level;
    }

    /**
     * Escribe el asiento inmutable del kardex. Si esto falla tras mutar el saldo,
     * DB::transaction revierte TODO (ERR-03B / InventorySyncException como red de seguridad).
     */
    private function postMovement(
        int $productId,
        int $warehouseId,
        InventoryMovementType $type,
        string $quantity,
        string $balanceAfter,
        ?string $unitCost = null,
        ?int $userId = null,
        ?int $stockTransferId = null,
        ?int $adjustmentId = null,
        ?int $purchaseOrderId = null,
        ?int $dispatchId = null,
        ?string $reason = null,
    ): InventoryMovement {
        try {
            return InventoryMovement::create([
                'product_id'              => $productId,
                'warehouse_id'            => $warehouseId,
                'user_id'                 => $userId,
                'type'                    => $type,
                'quantity'                => $quantity,
                'balance_after'           => $balanceAfter,
                'unit_cost'               => $unitCost,
                'stock_transfer_id'       => $stockTransferId,
                'inventory_adjustment_id' => $adjustmentId,
                'purchase_order_id'       => $purchaseOrderId,
                'dispatch_id'             => $dispatchId,
                'reason'                  => $reason,
            ]);
        } catch (Throwable $e) {
            // El rollback lo ejecuta DB::transaction; aquí solo traducimos a la excepción de dominio.
            throw new InventorySyncException;
        }
    }

    private function abs(string $decimal): string
    {
        return bccomp($decimal, '0', 3) < 0 ? bcmul($decimal, '-1', 3) : $decimal;
    }

    private function sku(int $productId): string
    {
        return (string) (Product::query()->whereKey($productId)->value('sku') ?? $productId);
    }

    private function warehouseName(int $warehouseId): string
    {
        return (string) (Warehouse::query()->whereKey($warehouseId)->value('name') ?? $warehouseId);
    }
}
