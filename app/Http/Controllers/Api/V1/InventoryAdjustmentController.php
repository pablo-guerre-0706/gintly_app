<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InventoryAdjustmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InventoryAdjustment\IndexInventoryAdjustmentRequest;
use App\Http\Requests\Api\V1\InventoryAdjustment\StoreInventoryAdjustmentRequest;
use App\Http\Resources\InventoryAdjustmentResource;
use App\Models\InventoryAdjustment;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class InventoryAdjustmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function index(IndexInventoryAdjustmentRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InventoryAdjustment::class);

        // IndexInventoryAdjustmentRequest valida filtros/orden/paginación (contrato MOD-03).
        // 'movements' expone producto/cantidad/balance (no viven en la cabecera del ajuste).
        $adjustments = InventoryAdjustment::query()
            ->with(['warehouse', 'user', 'movements'])
            ->when(
                $request->validated('warehouse_id'),
                fn ($q, $warehouseId) => $q->where('warehouse_id', $warehouseId)
            )
            ->when(
                $request->validated('type'),
                fn ($q, $type) => $q->where('type', $type)
            )
            ->when(
                $request->validated('physical_count_id'),
                fn ($q, $countId) => $q->where('physical_count_id', $countId)
            )
            ->orderBy($request->sortColumn('adjusted_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return InventoryAdjustmentResource::collection($adjustments);
    }

    public function store(StoreInventoryAdjustmentRequest $request): JsonResponse
    {
        $this->authorize('create', InventoryAdjustment::class);

        // DM3-03: solo merma/sobrante. El request entrega string; se hidrata el Enum para el contrato del Service.
        $adjustment = $this->inventory->ajustar(
            $request->user(),
            (int) $request->validated('warehouse_id'),
            (int) $request->validated('product_id'),
            InventoryAdjustmentType::from($request->validated('type')),
            (string) $request->validated('quantity'),
            (string) $request->validated('reason'),
        );

        // 'movements' incluye el asiento con producto/cantidad/balance del ajuste recién creado.
        return (new InventoryAdjustmentResource($adjustment->load(['warehouse', 'user', 'movements'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
