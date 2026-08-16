<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
        // BusinessScope aísla el tenant. Eager 'branch' para no incurrir en N+1 al exponer la sucursal.
        return WarehouseResource::collection(
            Warehouse::query()->with('branch')->orderBy('name')->paginate($request->integer('per_page', 15)),
        );
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
        $warehouse->delete(); // soft delete; el name_lock parcial libera el nombre al desactivar

        return response()->noContent();
    }
}
