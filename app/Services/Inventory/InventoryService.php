<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\InventoryAdjustmentType;
use App\Enums\InventoryMovementType;
use App\Enums\PhysicalCountStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCountStateException;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\PhysicalCount;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class InventoryService
{
    private const QTY_SCALE = 3;

    private const COST_SCALE = 4;

    /**
     * Aplica un ajuste directo (merma/sobrante/corrección) sobre el saldo y
     * escribe el asiento de kardex, de forma atómica.
     *
     * @param  numeric-string|float|int  $quantity  Magnitud SIEMPRE positiva.
     */
    public function ajustar(
        User $actor,
        int $warehouseId,
        int $productId,
        InventoryAdjustmentType $type,
        string $quantity,
        string $reason,
        ?int $physicalCountId = null
    ): InventoryAdjustment {
        return DB::transaction(function () use ($actor, $warehouseId, $productId, $type, $quantity, $reason, $physicalCountId): InventoryAdjustment {
            $stock = $this->lockStock($actor->business_id, $productId, $warehouseId);

            // El signo lo fija el tipo del ajuste (H-26), no el del movimiento.
            $signedQty = $type->hasFixedDirection()
                ? bcmul($quantity, (string) $type->directionFactor(), self::QTY_SCALE)
                : $quantity; // corrección: signo ya resuelto por el llamador

            $newQuantity = bcadd((string) $stock->quantity, $signedQty, self::QTY_SCALE);

            $this->assertNonNegative($newQuantity, $productId, $warehouseId, $stock);

            $adjustment = InventoryAdjustment::query()->create([
                'warehouse_id'      => $warehouseId,
                'physical_count_id' => $physicalCountId,
                'type'              => $type,
                'reason'            => $reason,
                'adjusted_at'       => Carbon::now(),
            ]);
            $adjustment->user_id = $actor->id; // derivado de sesión (D-7)
            $adjustment->save();

            $this->writeMovement(
                actor: $actor,
                productId: $productId,
                warehouseId: $warehouseId,
                type: InventoryMovementType::Ajuste,
                magnitude: $quantity,
                balanceAfter: $newQuantity,
                unitCost: (string) $stock->average_cost,
                reason: $reason,
                adjustmentId: $adjustment->id,
            );

            $stock->quantity = $newQuantity;
            $stock->save();

            return $adjustment;
        });
    }

    // Aplica un conteo físico
    public function ajustarPorConteo(User $actor, PhysicalCount $count): PhysicalCount
    {
        return DB::transaction(function () use ($actor, $count): PhysicalCount {
            // Relee el conteo bajo lock: evita doble aplicación concurrente.
            $count = PhysicalCount::query()
                ->whereKey($count->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $count->status->isOpen()) {
                throw InvalidCountStateException::countNotOpen($count->id);
            }

            $stock = $this->lockStock($actor->business_id, $count->product_id, $count->warehouse_id);

            $difference = bcsub((string) $count->counted_quantity, (string) $stock->quantity, self::QTY_SCALE);

            // Sin diferencia no hay ajuste; el conteo se marca ajustado igual.
            if (bccomp($difference, '0', self::QTY_SCALE) !== 0) {
                // La corrección lleva el signo de la diferencia (puede subir o bajar).
                $adjustment = InventoryAdjustment::query()->create([
                    'warehouse_id'      => $count->warehouse_id,
                    'physical_count_id' => $count->id,
                    'type'              => InventoryAdjustmentType::Correccion,
                    'reason'            => 'Ajuste por conteo físico #'.$count->id,
                    'adjusted_at'       => Carbon::now(),
                ]);
                $adjustment->user_id = $actor->id;
                $adjustment->save();

                $this->writeMovement(
                    actor: $actor,
                    productId: $count->product_id,
                    warehouseId: $count->warehouse_id,
                    type: InventoryMovementType::Ajuste,
                    magnitude: $this->absolute($difference),
                    balanceAfter: (string) $count->counted_quantity,
                    unitCost: (string) $stock->average_cost,
                    reason: 'Ajuste por conteo físico #'.$count->id,
                    adjustmentId: $adjustment->id,
                );

                $stock->quantity = (string) $count->counted_quantity;
                $stock->save();
            }

            $count->status = PhysicalCountStatus::Ajustado;
            $count->save();

            return $count->refresh();
        });
    }

    // Marca un conteo como justificado sin tocar el saldo.
    public function justificarConteo(User $actor, PhysicalCount $count, string $reason): PhysicalCount
    {
        return DB::transaction(function () use ($count, $reason): PhysicalCount {
            $count = PhysicalCount::query()
                ->whereKey($count->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $count->status->isOpen()) {
                throw InvalidCountStateException::countNotOpen($count->id);
            }

            $count->status = PhysicalCountStatus::Justificado;
            // notes conserva la observación original del conteo; el motivo de la
            // justificación se antepone para dejar la traza completa.
            $count->notes = trim($reason.' | '.(string) $count->notes);
            $count->save();

            return $count->refresh();
        });
    }

    // Salida física de stock por traspaso (lo consume StockTransferService).
    public function descontarPorTraspaso(User $actor, int $warehouseId, int $productId, string $quantity, int $transferId): void
    {
        $stock = $this->lockStock($actor->business_id, $productId, $warehouseId);

        $newQuantity = bcsub((string) $stock->quantity, $quantity, self::QTY_SCALE);

        $this->assertNonNegative($newQuantity, $productId, $warehouseId, $stock);

        $this->writeMovement(
            actor: $actor,
            productId: $productId,
            warehouseId: $warehouseId,
            type: InventoryMovementType::Traspaso,
            magnitude: $quantity,
            balanceAfter: $newQuantity,
            unitCost: (string) $stock->average_cost,
            reason: 'Salida por traspaso #'.$transferId,
            transferId: $transferId,
        );

        $stock->quantity = $newQuantity;
        $stock->save();
    }

    // Entrada física de stock por traspaso en la bodega destino.
    public function ingresarPorTraspaso(User $actor, int $warehouseId, int $productId, string $quantity, string $unitCost, int $transferId): void
    {
        $stock = $this->lockOrCreateStock($actor->business_id, $productId, $warehouseId);

        $newQuantity = bcadd((string) $stock->quantity, $quantity, self::QTY_SCALE);

        // Costo promedio ponderado: (qtyPrev*costPrev + qtyIn*costIn) / qtyNew.
        $newAvgCost = $this->weightedAverageCost(
            (string) $stock->quantity,
            (string) $stock->average_cost,
            $quantity,
            $unitCost,
            $newQuantity
        );

        $this->writeMovement(
            actor: $actor,
            productId: $productId,
            warehouseId: $warehouseId,
            type: InventoryMovementType::Traspaso,
            magnitude: $quantity,
            balanceAfter: $newQuantity,
            unitCost: $unitCost,
            reason: 'Entrada por traspaso #'.$transferId,
            transferId: $transferId,
        );

        $stock->quantity = $newQuantity;
        $stock->average_cost = $newAvgCost;
        $stock->save();
    }

    private function lockStock(int $businessId, int $productId, int $warehouseId): StockLevel
    {
        $stock = StockLevel::query()
            ->where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($stock === null) {
            throw InsufficientStockException::make($productId, $warehouseId, '0.000', 'N/D');
        }

        return $stock;
    }

    // Como lockStock, pero crea el saldo en cero si no existe (entradas).
    private function lockOrCreateStock(int $businessId, int $productId, int $warehouseId): StockLevel
    {
        $stock = StockLevel::query()
            ->where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($stock !== null) {
            return $stock;
        }

        // Creación por asignación directa: business_id no está en fillable.
        $stock = new StockLevel();
        $stock->business_id = $businessId;
        $stock->product_id = $productId;
        $stock->warehouse_id = $warehouseId;
        $stock->quantity = '0.000';
        $stock->reserved_quantity = '0.000';
        $stock->average_cost = '0.0000';
        $stock->save();

        // Re-lee bajo lock para serializar contra otra creación concurrente.
        return StockLevel::query()->whereKey($stock->getKey())->lockForUpdate()->firstOrFail();
    }

    //Inserta el asiento inmutable de kardex. balance_after es la foto del saldo
    //tras el movimiento. Solo un origen (chk_movement_single_origin).
    private function writeMovement(
        User $actor,
        int $productId,
        int $warehouseId,
        InventoryMovementType $type,
        string $magnitude,
        string $balanceAfter,
        ?string $unitCost,
        string $reason,
        ?int $transferId = null,
        ?int $adjustmentId = null,
    ): InventoryMovement {
        $movement = new InventoryMovement();
        $movement->business_id = $actor->business_id;
        $movement->product_id = $productId;
        $movement->warehouse_id = $warehouseId;
        $movement->user_id = $actor->id;
        $movement->type = $type;
        $movement->quantity = $magnitude;
        $movement->balance_after = $balanceAfter;
        $movement->unit_cost = $unitCost;
        $movement->stock_transfer_id = $transferId;
        $movement->inventory_adjustment_id = $adjustmentId;
        $movement->reason = $reason;
        $movement->save();

        return $movement;
    }

    private function assertNonNegative(string $newQuantity, int $productId, int $warehouseId, StockLevel $stock): void
    {
        if (bccomp($newQuantity, '0', self::QTY_SCALE) < 0) {
            throw InsufficientStockException::make(
                $productId,
                $warehouseId,
                $stock->available,
                $this->absolute(bcsub($newQuantity, (string) $stock->quantity, self::QTY_SCALE))
            );
        }
    }

    private function weightedAverageCost(string $qtyPrev, string $costPrev, string $qtyIn, string $costIn, string $qtyNew): string
    {
        if (bccomp($qtyNew, '0', self::QTY_SCALE) === 0) {
            return $costPrev;
        }

        $valuePrev = bcmul($qtyPrev, $costPrev, self::COST_SCALE);
        $valueIn = bcmul($qtyIn, $costIn, self::COST_SCALE);
        $total = bcadd($valuePrev, $valueIn, self::COST_SCALE);

        return bcdiv($total, $qtyNew, self::COST_SCALE);
    }

    private function absolute(string $value): string
    {
        return bccomp($value, '0', self::QTY_SCALE) < 0
            ? bcmul($value, '-1', self::QTY_SCALE)
            : $value;
    }

    // MOD-04. Ingreso a inventario por recepción de compra conforme.
    public function ingresarPorCompra(
        User $actor,
        int $warehouseId,
        int $productId,
        string $quantity,
        string $unitCost,
        int $purchaseOrderId
    ): InventoryMovement {
        $stock = $this->lockOrCreateStock($actor->business_id, $productId, $warehouseId);

        $newQuantity = bcadd((string) $stock->quantity, $quantity, self::QTY_SCALE);

        $newAvgCost = $this->weightedAverageCost(
            (string) $stock->quantity,
            (string) $stock->average_cost,
            $quantity,
            $unitCost,
            $newQuantity
        );

        $movement = new InventoryMovement();
        $movement->business_id = $actor->business_id;
        $movement->product_id = $productId;
        $movement->warehouse_id = $warehouseId;
        $movement->user_id = $actor->id;
        $movement->type = InventoryMovementType::Entrada;
        $movement->quantity = $quantity;
        $movement->balance_after = $newQuantity;
        $movement->unit_cost = $unitCost;
        $movement->purchase_order_id = $purchaseOrderId;
        $movement->reason = 'Entrada por recepción de compra (orden #'.$purchaseOrderId.')';
        $movement->save();

        $stock->quantity = $newQuantity;
        $stock->average_cost = $newAvgCost;
        $stock->save();

        return $movement;
    }
}
