<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\IndexCategoryRequest;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CategoryService $categories,
    ) {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(IndexCategoryRequest $request): AnonymousResourceCollection
    {
        // BelongsToBusiness aísla el tenant. IndexCategoryRequest valida filtros/orden/paginación.

        // ?tree=true: árbol jerárquico completo (raíces con descendencia anidada), sin paginar.
        // Es una vista estructural completa: los filtros de lista no se aplican.
        if ($request->boolean('tree')) {
            $tree = Category::query()
                ->whereNull('parent_id')
                ->with('childrenRecursive')
                ->orderBy('name')
                ->get();

            return CategoryResource::collection($tree);
        }

        // Lista plana paginada con filtros del contrato (parent_id, is_active, search).
        $categories = Category::query()
            ->with('children')
            ->when(
                $request->has('parent_id'),
                fn ($q) => $q->where('parent_id', $request->integer('parent_id'))
            )
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active'))
            )
            ->when(
                $request->validated('search'),
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
            )
            ->orderBy($request->sortColumn('name'), $request->sortDirection('asc'))
            ->paginate($request->perPage());

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        // DFS anti-ciclo ascendente bajo lock (H-17 indirecto) y auto-fill de business_id -> Service/trait.
        $category = $this->categories->create($request->validated());

        return (new CategoryResource($category->load('children')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource(
            $this->categories->update($category, $request->validated())->load('children'),
        );
    }

    public function destroy(Category $category): Response
    {
        // Soft siempre; RestrictDeleteException (409) si hay dependientes -> lo decide el Service.
        $this->categories->delete($category);

        return response()->noContent();
    }
}
