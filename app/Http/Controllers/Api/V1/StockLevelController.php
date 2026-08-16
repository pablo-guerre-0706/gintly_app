<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockLevel\IndexStockLevelRequest;
use App\Http\Requests\Api\V1\StockLevel\UpdateThresholdsRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StockLevelController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function index(IndexStockLevelRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockLevel::class);

        $stock = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->paginate($request->integer('per_page', 15));

        return StockLevelResource::collection($stock);
    }

    public function show(Product $product, Warehouse $warehouse): StockLevelResource
    {
        $stock = StockLevel::query()
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->with(['product', 'warehouse'])
            ->firstOrFail();

        $this->authorize('view', $stock);

        return new StockLevelResource($stock);
    }

    public function updateThresholds(
        UpdateThresholdsRequest $request,
        Product $product,
        Warehouse $warehouse,
    ): StockLevelResource {
        // La policy real exige la instancia del saldo. firstOrNew da contexto aunque el saldo aún no exista.
        $stock = StockLevel::firstOrNew([
            'product_id'   => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
        ]);
        $stock->business_id ??= $product->business_id; // contexto de tenant para la policy si es un saldo nuevo

        $this->authorize('updateThresholds', $stock);

        // DM3-02: el Service es el único que escribe min/max (fuera de $fillable), bajo lockForUpdate.
        $stock = $this->inventory->fijarUmbrales(
            $product,
            $warehouse,
            $request->validated('min_stock'),
            $request->validated('max_stock'),
        );

        return new StockLevelResource($stock->load(['product', 'warehouse']));
    }
}
