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

        // IndexStockTransferRequest valida filtros/orden/paginación (contrato MOD-03).
        $transfers = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'user', 'items'])
            ->when(
                $request->validated('from_warehouse_id'),
                fn ($q, $fromId) => $q->where('from_warehouse_id', $fromId)
            )
            ->when(
                $request->validated('to_warehouse_id'),
                fn ($q, $toId) => $q->where('to_warehouse_id', $toId)
            )
            ->when(
                $request->validated('status'),
                fn ($q, $status) => $q->where('status', $status)
            )
            ->orderBy($request->sortColumn('transferred_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

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

        return (new StockTransferResource($transfer->load(['fromWarehouse', 'toWarehouse', 'user', 'items.product'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(StockTransfer $stockTransfer): StockTransferResource
    {
        $this->authorize('view', $stockTransfer);

        return new StockTransferResource($stockTransfer->load(['fromWarehouse', 'toWarehouse', 'user', 'items.product']));
    }
}
