<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierStatus;
use App\Exceptions\InvalidPurchaseStateException;
use App\Exceptions\SupplierNotApprovedException;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SequenceGenerator;
use Illuminate\Support\Facades\DB;


// Orquesta órdenes de compra.
final class PurchaseOrderService
{
    public function __construct(
        private readonly SequenceGenerator $sequences,
    ) {
    }

    /**
     * @param  array<int, array{product_id: int, ordered_quantity: string, agreed_unit_cost: string}>  $items
     */
    public function crear(User $actor, int $supplierId, int $branchId, string $orderedAt, array $items, ?string $notes): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $supplierId, $branchId, $orderedAt, $items, $notes): PurchaseOrder {
            $this->assertSupplierApproved($actor->business_id, $supplierId);

            $code = $this->sequences->next($actor->business_id, 'purchase_order', 'OC-');

            $order = new PurchaseOrder();
            $order->business_id = $actor->business_id;
            $order->branch_id = $branchId;
            $order->supplier_id = $supplierId;
            $order->user_id = $actor->id;
            $order->code = $code;
            $order->status = PurchaseOrderStatus::Borrador;
            $order->ordered_at = $orderedAt;
            $order->notes = $notes;
            $order->expected_total = '0.00';
            $order->save();

            $expectedTotal = $this->syncItems($order, $items);

            $order->expected_total = $expectedTotal;
            $order->save();

            return $order->refresh()->load('items');
        });
    }

    /**
     * @param  array<int, array{product_id: int, ordered_quantity: string, agreed_unit_cost: string}>  $items
     */
    public function actualizar(PurchaseOrder $order, string $orderedAt, array $items, ?string $notes): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $orderedAt, $items, $notes): PurchaseOrder {
            $order = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $order->status->isEditable()) {
                throw InvalidPurchaseStateException::orderNotEditable($order->id);
            }

            $order->ordered_at = $orderedAt;
            $order->notes = $notes;
            $order->save();

            // Reemplazo total del detalle: se borran las líneas y se recrean.
            $order->items()->delete();
            $expectedTotal = $this->syncItems($order, $items);

            $order->expected_total = $expectedTotal;
            $order->save();

            return $order->refresh()->load('items');
        });
    }

    // Emisión con revalidación de proveedor aprobado
    public function emitir(User $actor, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order): PurchaseOrder {
            $order = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->status !== PurchaseOrderStatus::Borrador) {
                throw InvalidPurchaseStateException::orderNotIssuable($order->id);
            }

            $this->assertSupplierApproved($actor->business_id, $order->supplier_id);

            $order->status = PurchaseOrderStatus::Emitida;
            $order->save();

            return $order->refresh();
        });
    }

    public function cancelar(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            $order = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $order->status->canCancel()) {
                throw InvalidPurchaseStateException::orderNotCancellable($order->id);
            }

            $order->status = PurchaseOrderStatus::Cancelada;
            $order->save();

            return $order->refresh();
        });
    }

    /**
     * Crea las líneas y devuelve el expected_total como string bcmath escala 2.
     *
     * @param  array<int, array{product_id: int, ordered_quantity: string, agreed_unit_cost: string}>  $items
     */
    private function syncItems(PurchaseOrder $order, array $items): string
    {
        $expectedTotal = '0.00';

        foreach ($items as $item) {
            // line_total = ordered_quantity(3) × agreed_unit_cost(4), a escala 2.
            $lineTotal = bcmul($item['ordered_quantity'], $item['agreed_unit_cost'], 2);

            $order->items()->create([
                'product_id'        => $item['product_id'],
                'ordered_quantity'  => $item['ordered_quantity'],
                'received_quantity' => '0.000',
                'agreed_unit_cost'  => $item['agreed_unit_cost'],
                'line_total'        => $lineTotal,
            ]);

            $expectedTotal = bcadd($expectedTotal, $lineTotal, 2);
        }

        return $expectedTotal;
    }

    // Lee el estado del proveedor bajo lock. ERR-04B si no está aprobado.
    private function assertSupplierApproved(int $businessId, int $supplierId): void
    {
        $status = Supplier::query()
            ->where('business_id', $businessId)
            ->whereKey($supplierId)
            ->lockForUpdate()
            ->value('status');

        if ($status !== SupplierStatus::Aprobado->value) {
            throw SupplierNotApprovedException::make($supplierId);
        }
    }
}

