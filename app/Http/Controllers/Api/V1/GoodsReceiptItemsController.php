<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoodsReceiptItemResource;
use App\Models\GoodsReceipt;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GoodsReceiptItemsController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(GoodsReceipt $goodsReceipt): AnonymousResourceCollection
    {
        // La autoridad sobre la evidencia es la autoridad sobre la recepción padre.
        $this->authorize('view', $goodsReceipt);

        // Evidencia inmutable por recepción; GoodsReceiptItemResource carga 'product' condicionalmente.
        $goodsReceipt->load('items.product');

        return GoodsReceiptItemResource::collection($goodsReceipt->items);
    }
}
