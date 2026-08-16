<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\IndexProductRequest;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class ProductController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        // Eager de las FKs que expone ProductResource (anti N+1). BusinessScope aísla el tenant.
        $products = Product::query()
            ->with(['category', 'brand', 'unit'])
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        // SKU normalizado + pertenencia de category_id/brand_id/unit_id validados en el request (DM2-01).
        // H-16: type=service coacciona tracks_inventory=false en el modelo. business_id auto-fill por el trait.
        $product = Product::create($request->validated());

        return (new ProductResource($product->load(['category', 'brand', 'unit'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'brand', 'unit']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        // Inmutabilidad de SKU operativa (P4) validada en UpdateProductRequest::after() (DM2-05).
        $product->update($request->validated());

        return new ProductResource($product->load(['category', 'brand', 'unit']));
    }

    public function destroy(Product $product): Response
    {
        $product->delete(); // borrado lógico (BR-04); el SKU permanece ocupado a perpetuidad (D-2)

        return response()->noContent();
    }
}
