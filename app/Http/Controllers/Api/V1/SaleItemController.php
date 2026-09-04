<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sale\StoreSaleItemRequest;
use App\Http\Resources\SaleItemResource;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;


final class SaleItemController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly SaleService $sales)
    {
    }

    public function store(StoreSaleItemRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('manageItems', $sale);

        $validated = $request->validated();

        $item = $this->sales->agregarItem(
            $sale,
            (int) $validated['product_id'],
            (string) $validated['quantity'],
            (string) ($validated['discount_amount'] ?? '0.00') // Si no viene, enviamos '0.00'
        );

        return SaleItemResource::make($item->load('product'))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Sale $sale, SaleItem $item): Response
    {
        $this->authorize('manageItems', $sale);

        $this->sales->quitarItem($sale, $item);

        return response()->noContent();
    }
}
