<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\RestrictDeleteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Warehouse\IndexWarehouseRequest;
use App\Http\Requests\Api\V1\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Api\V1\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class WarehouseController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Warehouse::class, 'warehouse');
    }

    public function index(IndexWarehouseRequest $request): AnonymousResourceCollection
    {
        // BusinessScope aísla el tenant. IndexWarehouseRequest valida filtros/orden/paginación (contrato MOD-03).
        $warehouses = Warehouse::query()
            ->with('branch')
            ->when(
                $request->validated('branch_id'),
                fn ($q, $branchId) => $q->where('branch_id', $branchId)
            )
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active'))
            )
            ->when(
                $request->has('is_default'),
                fn ($q) => $q->where('is_default', $request->boolean('is_default'))
            )
            ->orderBy($request->sortColumn('name'), $request->sortDirection('asc'))
            ->paginate($request->perPage());

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        // business_id auto-fill (BelongsToBusiness). name_lock + default_lock garantizan unicidad y única default por sucursal.
        $warehouse = Warehouse::create($request->validated());

        return (new WarehouseResource($warehouse->load('branch')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse->load('branch'));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse->load('branch'));
    }

    public function destroy(Warehouse $warehouse): Response
    {
        // ERR-02B (409): la baja es lógica, por lo que la integridad se protege en dominio.
        // Se rechaza si tiene existencias/reservas, traspasos pendientes, o es la
        // predeterminada sin otra designada.
        if (($reason = $warehouse->deletionBlocker()) !== null) {
            throw new RestrictDeleteException("No es posible dar de baja la bodega: {$reason}.");
        }

        $warehouse->delete(); // soft delete; el name_lock parcial libera el nombre al desactivar

        return response()->noContent();
    }
}
