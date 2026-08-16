<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GoodsReceipt\IndexGoodsReceiptRequest;
use App\Http\Requests\Api\V1\GoodsReceipt\StoreGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Services\Purchasing\GoodsReceiptService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GoodsReceiptController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly GoodsReceiptService $receipts,
    ) {}

    public function index(IndexGoodsReceiptRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $receipts = GoodsReceipt::query()
            ->with(['purchaseOrder', 'warehouse'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return GoodsReceiptResource::collection($receipts);
    }

    public function store(StoreGoodsReceiptRequest $request): JsonResponse
    {
        // recibir() persiste todo y lanza PurchaseMatchException (409) DESPUÉS del commit. Sin transacción ni try/catch.
        // $tolerance es obligatorio (string): default '0' cuando el request lo omite. invoiceTotal sigue nullable.
        $invoiceTotal = $request->validated('supplier_invoice_total');

        $receipt = $this->receipts->recibir(
            $request->user(),
            (int) $request->validated('purchase_order_id'),
            (int) $request->validated('warehouse_id'),
            $request->validated('lines'),
            $request->validated('supplier_invoice_number'),
            $invoiceTotal === null ? null : (string) $invoiceTotal,
            (string) $request->validated('tolerance', '0'),
        );

        return (new GoodsReceiptResource($receipt->load(['purchaseOrder', 'warehouse', 'items.product', 'accountPayable'])))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('view', $goodsReceipt);

        return new GoodsReceiptResource(
            $goodsReceipt->load(['purchaseOrder', 'warehouse', 'items.product', 'accountPayable']),
        );
    }
}
