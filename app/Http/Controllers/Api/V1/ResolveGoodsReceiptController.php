<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GoodsReceipt\ResolveGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Services\Purchasing\GoodsReceiptService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class ResolveGoodsReceiptController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly GoodsReceiptService $receipts,
    ) {}

    public function __invoke(ResolveGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        // Autorización 'resolve' en ResolveGoodsReceiptRequest::authorize(). resolver(User, GR, string, ?string) devuelve el modelo.
        $receipt = $this->receipts->resolver(
            $request->user(),
            $goodsReceipt,
            $request->validated('resolution'),
            $request->validated('notes'),
        );

        return new GoodsReceiptResource($receipt->load(['purchaseOrder', 'warehouse', 'items.product', 'accountPayable']));
    }
}
