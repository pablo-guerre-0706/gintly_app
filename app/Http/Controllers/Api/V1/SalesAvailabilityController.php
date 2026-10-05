<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sales\IndexAvailabilityRequest;
use App\Http\Resources\ProductAvailabilityResource;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-03 (microcierre) · Disponibilidad para FACTURAR. Devuelve, por producto inventariable, existencia
 * registrada / reservado / disponible en la bodega PREDETERMINADA de la sucursal (misma resolución que la
 * reserva de facturación), acotado al negocio de sesión. No concede acceso general a bodegas ni a costos.
 */
final class SalesAvailabilityController extends Controller
{
    public function index(IndexAvailabilityRequest $request): AnonymousResourceCollection
    {
        $businessId = (int) $request->user()->business_id;
        $branchId   = $request->effectiveBranchId();

        // La bodega sobre la que se reservará al facturar: predeterminada o primera activa (fuente única).
        $warehouse = Warehouse::defaultForBranch($businessId, $branchId);

        if ($warehouse === null) {
            // Sucursal sin bodega operativa: lista vacía (no es un error de autorización ni de datos del cliente).
            return ProductAvailabilityResource::collection(
                StockLevel::query()->whereRaw('1 = 0')->paginate($request->perPage())
            );
        }

        $stock = StockLevel::query()
            ->with(['product.unit'])
            ->where('warehouse_id', $warehouse->id)
            // Solo productos que controlan inventario: los servicios no tienen 'disponible' que reservar.
            ->whereHas('product', fn ($p) => $p->where('tracks_inventory', true))
            ->when(
                $request->validated('product_id'),
                fn ($q, $productId) => $q->where('product_id', $productId)
            )
            ->when(
                $request->validated('search'),
                fn ($q, $search) => $q->whereHas('product', fn ($p) => $p
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"))
            )
            ->orderBy($request->sortColumn('updated_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return ProductAvailabilityResource::collection($stock);
    }
}
