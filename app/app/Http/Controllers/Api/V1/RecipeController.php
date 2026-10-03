<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Recipe\StoreRecipeLineRequest;
use App\Http\Requests\Api\V1\Recipe\UpdateRecipeLineRequest;
use App\Http\Resources\RecipeLineResource;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Services\Catalog\RecipeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class RecipeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RecipeService $recipes,
    ) {}

    public function index(Product $compound): AnonymousResourceCollection
    {
        // Sin RecipePolicy: la autoridad sobre la receta es la autoridad sobre el producto compuesto padre.
        $this->authorize('view', $compound);

        // ProductRecipe usa BelongsToBusiness (aísla tenant) + filtro por el compuesto de la ruta. Eager anti N+1.
        $lines = ProductRecipe::query()
            ->where('compound_id', $compound->getKey())
            ->with(['ingredient', 'unit'])
            ->get();

        return RecipeLineResource::collection($lines);
    }

    public function store(StoreRecipeLineRequest $request, Product $compound): JsonResponse
    {
        $this->authorize('update', $compound);

        // DM2-03: naturaleza (canHaveRecipe/canBeIngredient) ya validada en el request.
        // H-20: la línea pertenece al compuesto de la ruta. DFS anti-ciclo descendente + estado en el Service.
        $line = $this->recipes->addLine($compound, $request->validated());

        return (new RecipeLineResource($line->load(['ingredient', 'unit'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $compound, ProductRecipe $line): RecipeLineResource
    {
        // scopeBindings garantiza que $line pertenece a $compound; si no, 404 antes de llegar aquí.
        $this->authorize('view', $compound);

        return new RecipeLineResource($line->load(['ingredient', 'unit']));
    }

    public function update(UpdateRecipeLineRequest $request, Product $compound, ProductRecipe $line): RecipeLineResource
    {
        $this->authorize('update', $compound);

        return new RecipeLineResource(
            $this->recipes->updateLine($line, $request->validated())->load(['ingredient', 'unit']),
        );
    }

    public function destroy(Product $compound, ProductRecipe $line): Response
    {
        $this->authorize('update', $compound);

        $this->recipes->deleteLine($line);

        return response()->noContent();
    }
}
