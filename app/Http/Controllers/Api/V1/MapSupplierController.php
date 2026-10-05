<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SupplierStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MapSupplierResource;
use App\Models\Supplier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-04 · API del mapa de proveedores. Expone SOLO proveedores APROBADOS, ACTIVOS y con al menos una
 * ubicación CONFIRMADA, aislados por el business_id de la sesión (BusinessScope). Un proveedor suspendido
 * (status != aprobado), inactivo o eliminado (soft-delete) desaparece del mapa automáticamente.
 */
final class MapSupplierController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class); // proveedores.ver

        $suppliers = Supplier::query()
            ->where('status', SupplierStatus::Aprobado->value)
            ->where('is_active', true)
            ->whereHas('locations', fn ($q) => $q->whereNotNull('confirmed_at'))
            ->with(['locations' => fn ($q) => $q->whereNotNull('confirmed_at')->orderByDesc('is_primary')->orderBy('id')])
            ->orderBy('name')
            ->get();

        return MapSupplierResource::collection($suppliers);
    }
}
