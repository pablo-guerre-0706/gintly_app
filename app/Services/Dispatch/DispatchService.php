<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Enums\DeliveryState;
use App\Enums\DispatchStatus;
use App\Exceptions\DispatchExceedsBalanceException;
use App\Exceptions\DispatchOnVoidedInvoiceException;
use App\Exceptions\InvalidDispatchStateException;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Invoice;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Support\SequenceGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DispatchService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SequenceGenerator $sequences,
    ) {
    }

    // =====================================================================
    // RF-09-01 · Registro de retiro (total o parcial)
    // =====================================================================
    /** @param array{invoice_id:int, received_by?:string|null, notes?:string|null, lines:array<int,array{sale_item_id:int, quantity:string}>} $data */
    public function registrar(User $actor, array $data): Dispatch
    {
        return DB::transaction(function () use ($data, $actor): Dispatch {
            // Bloquea la factura: ninguna anulación concurrente puede colarse (ERR-09B).
            $invoice = Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();

            if ($invoice->status->value === 'anulada') {
                throw new DispatchOnVoidedInvoiceException($invoice->id);
            }

            $warehouse      = $this->bodegaOrigen($invoice);
            $invoiceSaleIds = $invoice->sales()->pluck('sales.id'); // Pivote invoice_sale (MOD-07).

            $dispatch = new Dispatch();
            $dispatch->branch_id     = $invoice->branch_id;
            $dispatch->invoice_id    = $invoice->id;
            $dispatch->warehouse_id  = $warehouse->id;
            $dispatch->received_by   = $data['received_by'] ?? null;
            $dispatch->notes         = $data['notes'] ?? null;
            $dispatch->user_id       = $actor->id;                             // Responsable (no-repudio).
            $dispatch->code          = $this->sequences->next($actor->business_id, 'dispatch', 'D-'); // Folio interno.

            $dispatch->status        = DispatchStatus::Registrado;
            $dispatch->dispatched_at = now();
            $dispatch->save();

            foreach ($data['lines'] as $line) {
                $saleItem = SaleItem::query()->whereKey($line['sale_item_id'])->lockForUpdate()->firstOrFail();
                $qty      = (string) $line['quantity'];

                // Coherencia: la línea pertenece a la factura del retiro.
                if (! $invoiceSaleIds->contains($saleItem->sale_id)) {
                    throw InvalidDispatchStateException::lineNotOnInvoice($saleItem->id);
                }

                // Servicios no despachan (RF-09-03).
                if ($saleItem->product->type->value === 'service') {
                    throw InvalidDispatchStateException::serviceNotDispatchable($saleItem->id);
                }

                // Saldo pendiente de la línea = facturado − retirado acumulado.
                $pending = bcsub((string) $saleItem->quantity, (string) $saleItem->dispatched_quantity, 3);
                if (bccomp($qty, $pending, 3) > 0) {
                    throw new DispatchExceedsBalanceException($saleItem->id, $pending, $qty);
                }

                // Descuento físico real + consumo de reserva + kardex (compuestos explotan insumos).
                $this->inventory->retirar($saleItem, $qty, $warehouse, $dispatch);

                // Acumulado materializado (fuera de fillable). chk_sale_item_dispatch_not_exceed respalda.
                $saleItem->dispatched_quantity = bcadd((string) $saleItem->dispatched_quantity, $qty, 3);
                $saleItem->save();

                DispatchItem::create([
                    'dispatch_id'  => $dispatch->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id'   => $saleItem->product_id,
                    'quantity'     => $qty,
                ]);
            }

            return $dispatch->fresh(['items', 'warehouse']);
        });
    }

    // =====================================================================
    // RF-09-04 · Reversión de retiro (ROL-02)
    // =====================================================================
    public function revertir(Dispatch $dispatch, string $revertReason): Dispatch
    {
        return DB::transaction(function () use ($dispatch, $revertReason): Dispatch {
            $dispatch = Dispatch::query()->whereKey($dispatch->getKey())
                ->with('items')->lockForUpdate()->firstOrFail();

            if (! $dispatch->canRevert()) {
                throw InvalidDispatchStateException::alreadyReverted();
            }

            $warehouse = Warehouse::query()->whereKey($dispatch->warehouse_id)->firstOrFail();

            foreach ($dispatch->items as $item) {
                $saleItem = SaleItem::query()->whereKey($item->sale_item_id)->lockForUpdate()->firstOrFail();

                // Reingresa a bodega origen + RE-RESERVA (factura viva) + kardex entrada.
                $this->inventory->reingresar($saleItem, (string) $item->quantity, $warehouse, $dispatch);

                // Devuelve la cantidad al saldo pendiente.
                $saleItem->dispatched_quantity = bcsub(
                    (string) $saleItem->dispatched_quantity,
                    (string) $item->quantity,
                    3
                );
                $saleItem->save();
            }

            // No se borra: se marca revertido con trazabilidad (chk_dispatch_revert_coherence respalda).
            $dispatch->status        = DispatchStatus::Revertido;
            $dispatch->reverted_by   = Auth::id();
            $dispatch->reverted_at   = now();
            $dispatch->revert_reason = $revertReason;
            $dispatch->save();

            return $dispatch->fresh(['items']);
        });
    }

    // =====================================================================
    // RF-09-02 · Saldo pendiente de entrega consolidado (solo lectura)
    // =====================================================================
    /** @return array{invoice_id:int, delivery_state:DeliveryState, lines:Collection<int, array<string,mixed>>} */
    public function estadoEntrega(Invoice $invoice): array
    {
        $saleIds = $invoice->sales()->pluck('sales.id');
        $items   = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->with('product:id,type')
            ->get();

        $lines = $items->map(function (SaleItem $item): array {
            $pending = bcsub((string) $item->quantity, (string) $item->dispatched_quantity, 3);

            return [
                'sale_item_id'        => $item->id,
                'product_id'          => $item->product_id,
                'description'         => $item->description,
                'invoiced_quantity'   => (string) $item->quantity,
                'dispatched_quantity' => (string) $item->dispatched_quantity,
                'pending_quantity'    => $pending,
            ];
        });

        // El estado se deriva SOLO de las líneas entregables (excluye servicios).
        $deliverable = $items->reject(fn (SaleItem $i): bool => $i->product->type->value === 'service');

        return [
            'invoice_id'     => $invoice->id,
            'delivery_state' => $this->derivarEstado($deliverable),
            'lines'          => $lines,
        ];
    }

    // ---------------- Helpers ----------------

    private function bodegaOrigen(Invoice $invoice): Warehouse
    {
        $warehouse = Warehouse::query()
            ->where('branch_id', $invoice->branch_id)
            ->where('is_default', true)
            ->first();

        if ($warehouse === null) {
            throw InvalidDispatchStateException::noDefaultWarehouse($invoice->branch_id);
        }

        return $warehouse;
    }

    /** @param Collection<int, SaleItem> $deliverable */
    private function derivarEstado(Collection $deliverable): DeliveryState
    {
        if ($deliverable->isEmpty()) {
            return DeliveryState::Completado; // Sin bienes entregables: nada que despachar.
        }

        $anyDispatched = $deliverable->contains(
            fn (SaleItem $i): bool => bccomp((string) $i->dispatched_quantity, '0.000', 3) > 0
        );
        $allDispatched = $deliverable->every(
            fn (SaleItem $i): bool => bccomp((string) $i->dispatched_quantity, (string) $i->quantity, 3) >= 0
        );

        return DeliveryState::derive($anyDispatched, $allDispatched);
    }
}
