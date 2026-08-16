<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class IssuePurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseOrderService $orders,
    ) {}

    public function __invoke(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('issue', $purchaseOrder);

        // emitir(User, PO): revalida proveedor aprobado bajo lock (H-36). borrador -> emitida.
        $order = $this->orders->emitir($request->user(), $purchaseOrder);

        return new PurchaseOrderResource($order->load(['supplier', 'branch', 'items.product']));
    }
}
