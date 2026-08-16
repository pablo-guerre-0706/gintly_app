<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Unit\IndexUnitRequest;
use App\Http\Requests\Api\V1\Unit\StoreUnitRequest;
use App\Http\Requests\Api\V1\Unit\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class UnitController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(UnitOfMeasure::class, 'unit');
    }

    public function index(IndexUnitRequest $request): AnonymousResourceCollection
    {
        return UnitResource::collection(
            UnitOfMeasure::query()->orderBy('abbreviation')->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = UnitOfMeasure::create($request->validated()); // unicidad solo por abbreviation (D-5)

        return (new UnitResource($unit))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(UnitOfMeasure $unit): UnitResource
    {
        return new UnitResource($unit);
    }

    public function update(UpdateUnitRequest $request, UnitOfMeasure $unit): UnitResource
    {
        $unit->update($request->validated());

        return new UnitResource($unit);
    }

    public function destroy(UnitOfMeasure $unit): Response
    {
        // Borrado físico (sin SD). El guard booted()::deleting() lanza RestrictDeleteException (409). Slim total.
        $unit->delete();

        return response()->noContent();
    }
}
