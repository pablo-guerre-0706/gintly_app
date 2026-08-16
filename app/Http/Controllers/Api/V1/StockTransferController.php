<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockTransfer\IndexStockTransferRequest;
use App\Http\Requests\Api\V1\StockTransfer\StoreStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class StockTransferController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly StockTransferService $transfers,
    ) {}

    public function index(IndexStockTransferRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockTransfer::class);

        $transfers = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'user'])
            ->latest('transferred_at')
            ->paginate($request->integer('per_page', 15));

        return StockTransferResource::collection($transfers);
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        // Autorización 'create' resuelta en StoreStockTransferRequest::authorize(); no se duplica aquí.
        // items[] es required en tu request -> se pasa directo, sin default. El Service hace bcmath sobre cada quantity.
        $transfer = $this->transfers->crear(
            $request->user(),
            (int) $request->validated('from_warehouse_id'),
            (int) $request->validated('to_warehouse_id'),
            $request->validated('items'),
            $request->validated('notes'),
        );

        return (new StockTransferResource($transfer->load(['fromWarehouse', 'toWarehouse', 'user'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(StockTransfer $stockTransfer): StockTransferResource
    {
        $this->authorize('view', $stockTransfer);

        return new StockTransferResource($stockTransfer->load(['fromWarehouse', 'toWarehouse', 'user']));
    }
}
