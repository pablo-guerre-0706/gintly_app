<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class CancelPurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseOrderService $orders,
    ) {}

    public function __invoke(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        // cancelar() no recibe actor: la identidad ya la resuelve el Gate en authorize().
        $this->authorize('cancel', $purchaseOrder);

        $order = $this->orders->cancelar($purchaseOrder);

        return new PurchaseOrderResource($order->load(['supplier', 'branch', 'items.product']));
    }
}
