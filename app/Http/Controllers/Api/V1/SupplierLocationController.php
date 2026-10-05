<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SupplierLocation\ConfirmSupplierLocationRequest;
use App\Http\Requests\Api\V1\SupplierLocation\StoreSupplierLocationRequest;
use App\Http\Requests\Api\V1\SupplierLocation\UpdateSupplierLocationRequest;
use App\Http\Resources\SupplierLocationResource;
use App\Models\Supplier;
use App\Models\SupplierLocation;
use App\Services\Purchasing\SupplierGeocodingService;
use App\Services\Purchasing\SupplierLocationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-04 · Ubicaciones de proveedor (sub-recurso). Capa HTTP delgada: valida en FormRequest, autoriza por
 * SupplierLocationPolicy y delega a SupplierLocationService / SupplierGeocodingService.
 */
final class SupplierLocationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SupplierLocationService $locations,
        private readonly SupplierGeocodingService $geocoding,
    ) {}

    public function index(Supplier $supplier): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [SupplierLocation::class, $supplier]);

        return SupplierLocationResource::collection(
            $supplier->locations()->orderByDesc('is_primary')->orderBy('id')->get()
        );
    }

    public function store(StoreSupplierLocationRequest $request, Supplier $supplier): JsonResponse
    {
        $location = $this->locations->crear($supplier, $request->validated());

        return SupplierLocationResource::make($location)->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateSupplierLocationRequest $request, Supplier $supplier, SupplierLocation $location): SupplierLocationResource
    {
        return SupplierLocationResource::make(
            $this->locations->actualizar($request->user(), $location, $request->validated())
        );
    }

    /** Solicita geocodificación del marcador. No confirma; si no hay proveedor o falla, queda pendiente. */
    public function geocode(Request $request, Supplier $supplier, SupplierLocation $location): SupplierLocationResource
    {
        $this->authorize('manage', [SupplierLocation::class, $supplier]);

        $this->geocoding->geocodificar($location);

        return SupplierLocationResource::make($location->refresh());
    }

    /** Confirma o corrige manualmente el marcador (lo pone en el mapa). */
    public function confirm(ConfirmSupplierLocationRequest $request, Supplier $supplier, SupplierLocation $location): SupplierLocationResource
    {
        return SupplierLocationResource::make(
            $this->locations->confirmar($request->user(), $location, $request->validated())
        );
    }
}
