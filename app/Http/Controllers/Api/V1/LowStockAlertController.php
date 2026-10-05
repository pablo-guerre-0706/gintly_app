<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Inventory\IndexLowStockAlertRequest;
use App\Http\Resources\LowStockAlertResource;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-03 (microcierre) · Avisos de mínimo. Lista DERIVADA (sin tabla ni duplicado) de los saldos cuyo
 * disponible alcanzó o cayó bajo el mínimo configurado. Visible para ROL-01/02 y el bodeguero de la bodega
 * ASIGNADA. Separado del catálogo cerrado de anomalías; al ser derivado del estado vivo, se actualiza solo
 * cuando cambia el stock o la reserva y no genera avisos duplicados.
 */
final class LowStockAlertController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexLowStockAlertRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockLevel::class);

        $user = $request->user();

        $alerts = StockLevel::query()
            ->with(['product.unit', 'warehouse'])
            // ROL-03: solo sus bodegas asignadas; ROL-01/02: alcance de negocio.
            ->forOperatorWarehouses($user)
            // Solo filas con mínimo configurado cuyo disponible ≤ mínimo (si no hay mínimo, no se inventa).
            ->availableAtOrBelowMin()
            ->when(
                $request->validated('warehouse_id'),
                fn ($q, $warehouseId) => $q->where('warehouse_id', $warehouseId)
            )
            ->when(
                $request->validated('branch_id'),
                fn ($q, $branchId) => $q->whereHas('warehouse', fn ($w) => $w->where('branch_id', $branchId))
            )
            ->when(
                $request->validated('product_id'),
                fn ($q, $productId) => $q->where('product_id', $productId)
            )
            ->orderBy($request->sortColumn('updated_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        // La ACCIÓN de reposición (iniciar una orden) solo se habilita a quien pueda crear órdenes de compra.
        // Fuente única: PurchaseOrderPolicy::create (compras.crear para ROL-03, nivel para ROL-01/02). No se
        // crea ni aprueba orden alguna aquí; es solo el permiso para ofrecer la acción en la interfaz.
        $canRequestPurchase = $user->can('create', PurchaseOrder::class);

        return LowStockAlertResource::collection($alerts)
            ->additional(['meta' => ['can_request_purchase' => $canRequestPurchase]]);
    }
}
