<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PurchaseOrder\IndexPurchaseOrderRequest;
use App\Http\Requests\Api\V1\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\Api\V1\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseOrderService $orders,
    ) {}

    public function index(IndexPurchaseOrderRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::query()
            ->with(['supplier', 'branch'])
            ->latest('ordered_at')
            ->paginate($request->integer('per_page', 15));

        return PurchaseOrderResource::collection($orders);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        // crear(): actor + primitivos + items. code (D-11) y user_id (D-12) los fija el Service.
        $order = $this->orders->crear(
            $request->user(),
            (int) $request->validated('supplier_id'),
            (int) $request->validated('branch_id'),
            $request->validated('ordered_at'),
            $request->validated('items'),
            $request->validated('notes'),
        );

        return (new PurchaseOrderResource($order->load(['supplier', 'branch', 'items.product'])))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder->load(['supplier', 'branch', 'items.product']));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        // actualizar() NO recibe $actor y exige ordered_at + items no nulos (ver nota de presencia al pie).
        $order = $this->orders->actualizar(
            $purchaseOrder,
            $request->validated('ordered_at'),
            $request->validated('items'),
            $request->validated('notes'),
        );

        return new PurchaseOrderResource($order->load(['supplier', 'branch', 'items.product']));
    }
}
