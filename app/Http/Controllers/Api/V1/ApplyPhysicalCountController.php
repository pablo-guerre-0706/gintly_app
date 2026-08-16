<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhysicalCountResource;
use App\Models\PhysicalCount;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class ApplyPhysicalCountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function __invoke(Request $request, PhysicalCount $physicalCount): PhysicalCountResource
    {
        $this->authorize('apply', $physicalCount);

        // única vía de la corrección. Devuelve el conteo actualizado ('ajustado').
        $count = $this->inventory->ajustarPorConteo($request->user(), $physicalCount);

        return new PhysicalCountResource($count->load(['product', 'warehouse', 'user']));
    }
}
