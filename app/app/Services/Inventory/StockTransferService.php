<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockTransferStatus;
use App\Exceptions\InvalidCountStateException;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\SequenceGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Orquesta el traspaso entre bodegas/
final class StockTransferService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SequenceGenerator $sequences,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: string}>  $items
     */
    public function crear(User $actor, int $fromWarehouseId, int $toWarehouseId, array $items, ?string $notes): StockTransfer
    {
        return DB::transaction(function () use ($actor, $fromWarehouseId, $toWarehouseId, $items, $notes): StockTransfer {
            // Invariante de dominio ROL-03 (no solo del FormRequest): el traspaso se ORIGINA en una bodega
            // de SU sucursal; el destino puede ser otra sucursal (regla vigente). Antes de persistir, para
            // que una llamada interna directa no omita la validación HTTP. ROL-01/ROL-02 no se acotan.
            $fromWarehouse = Warehouse::query()
                ->where('business_id', $actor->business_id)
                ->whereKey($fromWarehouseId)
                ->firstOrFail();
            $this->assertOperatorOriginBranch($actor, $fromWarehouse);

            // Folio interno atómico: TR-000001. Nunca lo envía el cliente (D-6).
            $code = $this->sequences->next($actor->business_id, 'stock_transfer', 'TR-');

            $transfer = new StockTransfer;
            $transfer->business_id = $actor->business_id;
            $transfer->from_warehouse_id = $fromWarehouseId;
            $transfer->to_warehouse_id = $toWarehouseId;
            $transfer->user_id = $actor->id; // no-repudio: debe fijarse ANTES del INSERT (columna NOT NULL).
            $transfer->code = $code;
            $transfer->status = StockTransferStatus::Pendiente;
            $transfer->transferred_at = Carbon::now();
            $transfer->notes = $notes;
            $transfer->save();

            // Opción A: las líneas se persisten al crear (antes se descartaban).
            // El costo NO se guarda: se deriva de la bodega origen al completar.
            foreach ($items as $item) {
                $line = new StockTransferItem;
                $line->business_id = $actor->business_id;
                $line->stock_transfer_id = $transfer->id;
                $line->product_id = (int) $item['product_id'];
                $line->quantity = (string) $item['quantity'];
                $line->save();
            }

            return $transfer->refresh();
        });
    }

    /**
     * Confirma el traspaso: mueve físicamente cada línea PERSISTIDA (el endpoint ya
     * no acepta ítems nuevos). Cada línea descuenta en origen y suma en destino bajo
     * lock, valorando la entrada al costo promedio de la bodega origen; si una línea
     * no tiene stock suficiente, la transacción entera se revierte (atomicidad).
     * Un traspaso pendiente sin líneas (dato antiguo previo a la opción A) se rechaza
     * con un error controlado: no se inventan ni aceptan líneas de reemplazo.
     */
    public function completar(User $actor, StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($actor, $transfer): StockTransfer {
            $transfer = StockTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $transfer->status->canTransition()) {
                throw InvalidCountStateException::transferNotPending($transfer->id);
            }

            // Líneas persistidas al crear, bloqueadas para serializar la confirmación.
            $items = StockTransferItem::query()
                ->where('stock_transfer_id', $transfer->id)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw InvalidCountStateException::transferHasNoLines($transfer->id);
            }

            foreach ($items as $item) {
                // El costo de entrada en destino = costo promedio de la bodega origen.
                $sourceCost = $this->inventory->descontarPorTraspaso(
                    actor: $actor,
                    warehouseId: $transfer->from_warehouse_id,
                    productId: (int) $item->product_id,
                    quantity: (string) $item->quantity,
                    transferId: $transfer->id,
                );

                $this->inventory->ingresarPorTraspaso(
                    actor: $actor,
                    warehouseId: $transfer->to_warehouse_id,
                    productId: (int) $item->product_id,
                    quantity: (string) $item->quantity,
                    unitCost: $sourceCost,
                    transferId: $transfer->id,
                );
            }

            $transfer->status = StockTransferStatus::Completado;
            $transfer->save();

            return $transfer->refresh();
        });
    }

    /**
     * Aislamiento de sucursal ROL-03 en la creación: la bodega ORIGEN debe ser de su sucursal
     * (from_warehouse.branch_id === actor.branch_id). Sin sucursal falla cerrado. ROL-01/ROL-02 no se acotan.
     */
    private function assertOperatorOriginBranch(User $actor, Warehouse $fromWarehouse): void
    {
        if (! $actor->isOperator()) {
            return;
        }

        $branchId = $actor->branch_id;

        if ($branchId === null || (int) $fromWarehouse->branch_id !== (int) $branchId) {
            throw new AuthorizationException('El traspaso debe originarse en una bodega de su sucursal.');
        }
    }

    public function cancelar(User $actor, StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer = StockTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $transfer->status->canTransition()) {
                throw InvalidCountStateException::transferNotPending($transfer->id);
            }

            $transfer->status = StockTransferStatus::Cancelado;
            $transfer->save();

            return $transfer->refresh();
        });
    }
}
