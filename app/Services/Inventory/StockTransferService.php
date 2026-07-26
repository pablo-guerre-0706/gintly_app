<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockTransferStatus;
use App\Exceptions\InvalidCountStateException;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


// Orquesta el traspaso entre bodegas/
final class StockTransferService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SequenceGenerator $sequences,
    ) {
    }

    /**
     * @param  array<int, array{product_id: int, quantity: string}>  $items
     */
    public function crear(User $actor, int $fromWarehouseId, int $toWarehouseId, array $items, ?string $notes): StockTransfer
    {
        return DB::transaction(function () use ($actor, $fromWarehouseId, $toWarehouseId, $items, $notes): StockTransfer {
            // Folio interno atómico: TR-000001. Nunca lo envía el cliente (D-6).
            $code = $this->sequences->next($actor->business_id, 'stock_transfer', 'TR-');

            $transfer = new StockTransfer();
            $transfer->business_id = $actor->business_id;
            $transfer->from_warehouse_id = $fromWarehouseId;
            $transfer->to_warehouse_id = $toWarehouseId;
            $transfer->code = $code;
            $transfer->status = StockTransferStatus::Pendiente;
            $transfer->transferred_at = Carbon::now();
            $transfer->notes = $notes;
            $transfer->save();
            $transfer->user_id = $actor->id;
            $transfer->save();

            return $transfer->refresh();
        });
    }

    /**
     * Confirma el traspaso: mueve físicamente cada ítem. Cada línea descuenta en
     * origen y suma en destino bajo lock; si una línea no tiene stock suficiente,
     * la transacción entera se revierte (atomicidad) y ningún ítem se mueve.
     *
     * @param  array<int, array{product_id: int, quantity: string, unit_cost?: string}>  $items
     */
    public function completar(User $actor, StockTransfer $transfer, array $items): StockTransfer
    {
        return DB::transaction(function () use ($actor, $transfer, $items): StockTransfer {
            $transfer = StockTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $transfer->status->canTransition()) {
                throw InvalidCountStateException::transferNotPending($transfer->id);
            }

            foreach ($items as $item) {
                $this->inventory->descontarPorTraspaso(
                    actor: $actor,
                    warehouseId: $transfer->from_warehouse_id,
                    productId: $item['product_id'],
                    quantity: $item['quantity'],
                    transferId: $transfer->id,
                );

                $this->inventory->ingresarPorTraspaso(
                    actor: $actor,
                    warehouseId: $transfer->to_warehouse_id,
                    productId: $item['product_id'],
                    quantity: $item['quantity'],
                    unitCost: $item['unit_cost'] ?? '0.0000',
                    transferId: $transfer->id,
                );
            }

            $transfer->status = StockTransferStatus::Completado;
            $transfer->save();

            return $transfer->refresh();
        });
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
