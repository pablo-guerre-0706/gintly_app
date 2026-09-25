<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Enums\DeliveryState;
use App\Enums\DispatchStatus;
use App\Enums\RoleName;
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
use Illuminate\Auth\Access\AuthorizationException;
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
    /** @param array{invoice_id:int, received_by:string, notes?:string|null, lines:array<int,array{sale_item_id:int, quantity:string}>} $data */
    public function registrar(User $actor, array $data): Dispatch
    {
        return DB::transaction(function () use ($data, $actor): Dispatch {
            // ORDEN DE LOCK CANÓNICO (1) Invoice: ninguna anulación concurrente puede colarse (ERR-09B).
            $invoice = Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();

            if ($invoice->status->value === 'anulada') {
                throw new DispatchOnVoidedInvoiceException($invoice->id);
            }

            // Aislamiento de sucursal (RF-09-01): ROL-03 solo despacha facturas de SU sucursal.
            $this->assertCanDispatchFromBranch($actor, $invoice);

            $warehouse      = $this->bodegaOrigen($invoice);
            $invoiceSaleIds = $invoice->sales()->pluck('sales.id'); // Pivote invoice_sale (MOD-07).

            // ORDEN DE LOCK CANÓNICO (2) SaleItems por ID ascendente. Se validan y preparan
            // ANTES de escribir nada (todo-o-nada): pertenencia, despachabilidad y saldo.
            $orderedLines = collect($data['lines'])
                ->sortBy(static fn (array $line): int => (int) $line['sale_item_id'])
                ->values();

            /** @var array<int, array{saleItem: SaleItem, qty: string}> $prepared */
            $prepared   = [];
            $productIds = [];

            foreach ($orderedLines as $line) {
                $saleItem = SaleItem::query()->whereKey($line['sale_item_id'])->lockForUpdate()->firstOrFail();
                $qty      = (string) $line['quantity'];

                // Coherencia: la línea pertenece a una venta de la factura del retiro.
                if (! $invoiceSaleIds->contains($saleItem->sale_id)) {
                    throw InvalidDispatchStateException::lineNotOnInvoice($saleItem->id);
                }

                // Servicios no despachan (RF-09-03), con mensaje específico.
                if ($saleItem->product->type->value === 'service') {
                    throw InvalidDispatchStateException::serviceNotDispatchable($saleItem->id);
                }

                // Productos que no controlan inventario tampoco generan retiro físico.
                if (! $this->esDespachable($saleItem)) {
                    throw InvalidDispatchStateException::notDispatchable($saleItem->id);
                }

                // Saldo pendiente de la línea = facturado − retirado acumulado.
                $pending = bcsub((string) $saleItem->quantity, (string) $saleItem->dispatched_quantity, 3);
                if (bccomp($qty, $pending, 3) > 0) {
                    throw new DispatchExceedsBalanceException($saleItem->id, $pending, $qty);
                }

                $prepared[]  = ['saleItem' => $saleItem, 'qty' => $qty];
                $productIds  = array_merge($productIds, $this->inventory->componentProductIds($saleItem));
            }

            // ORDEN DE LOCK CANÓNICO (3) Saldos de inventario/insumos por product_id ascendente.
            $this->inventory->lockStockRows((int) $actor->business_id, (int) $warehouse->id, $productIds);

            // ORDEN DE LOCK CANÓNICO (4) Dispatch y movimientos. Folio interno atómico y reversible
            // con la transacción (mismo savepoint): un rollback revierte también el contador.
            $dispatch = new Dispatch();
            $dispatch->branch_id     = $invoice->branch_id;
            $dispatch->invoice_id    = $invoice->id;
            $dispatch->warehouse_id  = $warehouse->id;
            $dispatch->received_by   = $data['received_by'];               // Receptor declarado (RF-09-01).
            $dispatch->notes         = $data['notes'] ?? null;
            $dispatch->user_id       = $actor->id;                         // Responsable (no-repudio).
            $dispatch->code          = $this->sequences->next((int) $actor->business_id, 'dispatch', 'D-');
            $dispatch->status        = DispatchStatus::Registrado;
            $dispatch->dispatched_at = now();
            $dispatch->save();

            foreach ($prepared as $row) {
                $saleItem = $row['saleItem'];
                $qty      = $row['qty'];

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
            // ORDEN DE LOCK CANÓNICO idéntico al registro: (1) Invoice → (2) SaleItems → (3) Stock → (4) Dispatch.
            $dispatch = Dispatch::query()->whereKey($dispatch->getKey())->lockForUpdate()->firstOrFail();

            $invoice = Invoice::query()->whereKey($dispatch->invoice_id)->lockForUpdate()->firstOrFail();

            if (! $dispatch->canRevert()) {
                throw InvalidDispatchStateException::alreadyReverted();
            }

            // Factura anulada tras el retiro: NO se re-reserva mercancía de un comprobante inválido.
            // Lo ya entregado se resuelve por devolución (MOD-10), no revirtiendo el retiro (ERR-09B análogo).
            if ($invoice->status->value === 'anulada') {
                throw InvalidDispatchStateException::cannotRevertVoidedInvoice($invoice->id);
            }

            $warehouse = Warehouse::query()->whereKey($dispatch->warehouse_id)->firstOrFail();

            // Líneas del retiro en orden de sale_item_id (orden único de lock de líneas).
            $items = $dispatch->items()->orderBy('sale_item_id')->get();

            /** @var array<int, array{saleItem: SaleItem, qty: string}> $prepared */
            $prepared   = [];
            $productIds = [];

            foreach ($items as $item) {
                $saleItem = SaleItem::query()->whereKey($item->sale_item_id)->lockForUpdate()->firstOrFail();
                $prepared[]  = ['saleItem' => $saleItem, 'qty' => (string) $item->quantity];
                $productIds  = array_merge($productIds, $this->inventory->componentProductIds($saleItem));
            }

            // Pre-bloqueo determinista de saldos por product_id ascendente (mismo orden que el registro).
            $this->inventory->lockStockRows((int) $dispatch->business_id, (int) $warehouse->id, $productIds);

            foreach ($prepared as $row) {
                $saleItem = $row['saleItem'];
                $qty      = $row['qty'];

                // Reingresa a bodega origen + RE-RESERVA (factura viva) + kardex entrada.
                $this->inventory->reingresar($saleItem, $qty, $warehouse, $dispatch);

                // Devuelve la cantidad al saldo pendiente.
                $saleItem->dispatched_quantity = bcsub((string) $saleItem->dispatched_quantity, $qty, 3);
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
            ->with('product:id,type,tracks_inventory')
            ->get();

        $lines = $items->map(function (SaleItem $item): array {
            $pending = bcsub((string) $item->quantity, (string) $item->dispatched_quantity, 3);

            return [
                'sale_item_id'        => $item->id,
                'product_id'          => $item->product_id,
                'description'         => $item->description,
                'is_dispatchable'     => $this->esDespachable($item),
                'invoiced_quantity'   => (string) $item->quantity,
                'dispatched_quantity' => (string) $item->dispatched_quantity,
                'pending_quantity'    => $pending,
            ];
        });

        // El estado se deriva SOLO de las líneas ENTREGABLES (excluye servicios y no inventariables).
        $deliverable = $items->filter(fn (SaleItem $i): bool => $this->esDespachable($i));

        return [
            'invoice_id'     => $invoice->id,
            'delivery_state' => $this->derivarEstado($deliverable),
            'lines'          => $lines,
        ];
    }

    // ---------------- Helpers ----------------

    /**
     * La bodega de origen es EXCLUSIVAMENTE la predeterminada y ACTIVA de la sucursal de
     * la factura (RF-09-01, sin fallback ni warehouse_id del request). El soft-delete queda
     * excluido por el global scope de SoftDeletes.
     */
    private function bodegaOrigen(Invoice $invoice): Warehouse
    {
        $warehouse = Warehouse::query()
            ->where('branch_id', $invoice->branch_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($warehouse === null) {
            throw InvalidDispatchStateException::noDefaultWarehouse($invoice->branch_id);
        }

        return $warehouse;
    }

    /**
     * Aislamiento de sucursal (RF-09-01, patrón de MOD-06): ROL-01/ROL-02 despachan
     * cualquier sucursal del negocio; ROL-03 solo la suya, y sin sucursal asignada se
     * rechaza de forma controlada (403), nunca un 500.
     */
    private function assertCanDispatchFromBranch(User $actor, Invoice $invoice): void
    {
        if ($actor->holdsAtLeast(RoleName::Admin)) {
            return;
        }

        if ($actor->branch_id === null) {
            throw new AuthorizationException('No tiene una sucursal asignada para registrar retiros.');
        }

        if ((int) $invoice->branch_id !== (int) $actor->branch_id) {
            throw new AuthorizationException('No puede despachar mercancía de una factura de otra sucursal.');
        }
    }

    /**
     * Una línea es entregable si explota a mercancía inventariable: los compuestos siempre
     * (descuentan sus insumos), y los simples solo si no son servicio y controlan inventario.
     */
    private function esDespachable(SaleItem $saleItem): bool
    {
        if ($saleItem->isCompound()) {
            return true;
        }

        $product = $saleItem->product;

        return $product !== null
            && $product->type->value !== 'service'
            && (bool) $product->tracks_inventory;
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
