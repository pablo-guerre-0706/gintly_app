<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InventoryMovement\IndexInventoryMovementRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InventoryMovementController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexInventoryMovementRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $validated = $request->validated();

        $movements = InventoryMovement::query()
            ->with(['product', 'warehouse', 'user'])
            ->when($validated['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($validated['warehouse_id'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            // Rango del trait HasDateRangeFilter: from/to ya normalizados a Carbon inclusivo por tus métodos.
            ->when($request->fromDateTime(), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->toDateTime(), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 25));

        return InventoryMovementResource::collection($movements);
    }
}
