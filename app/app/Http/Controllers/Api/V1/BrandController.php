<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Brand\IndexBrandRequest;
use App\Http\Requests\Api\V1\Brand\StoreBrandRequest;
use App\Http\Requests\Api\V1\Brand\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class BrandController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Brand::class, 'brand');
    }

    public function index(IndexBrandRequest $request): AnonymousResourceCollection
    {
        // CRUD plano (sin dominio complejo): mismo patrón que Branch/Business de MOD-01.
        return BrandResource::collection(
            Brand::query()->orderBy('name')->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated()); // business_id auto-fill por BelongsToBusiness

        return (new BrandResource($brand))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        $brand->update($request->validated());

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand): Response
    {
        $brand->delete(); // soft delete; products.brand_id es SETNULL, borrado inocuo

        return response()->noContent();
    }
}
