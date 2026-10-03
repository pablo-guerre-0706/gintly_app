<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PhysicalCount\JustifyPhysicalCountRequest;
use App\Http\Resources\PhysicalCountResource;
use App\Models\PhysicalCount;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class JustifyPhysicalCountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function __invoke(JustifyPhysicalCountRequest $request, PhysicalCount $physicalCount): PhysicalCountResource
    {
        $this->authorize('justify', $physicalCount);

        // D-8: motivo obligatorio. No mueve stock: marca 'justificado' y devuelve el modelo.
        $count = $this->inventory->justificarConteo(
            $request->user(),
            $physicalCount,
            $request->validated('reason'),
        );

        return new PhysicalCountResource($count->load(['product', 'warehouse', 'user']));
    }
}
