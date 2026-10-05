<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockLevel\IndexStockLevelRequest;
use App\Http\Requests\Api\V1\StockLevel\UpdateThresholdsRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\PhysicalCount;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

final class StockLevelController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function index(IndexStockLevelRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockLevel::class);

        // IndexStockLevelRequest valida filtros/orden/paginación (contrato MOD-03).
        // Microcierre MOD-03: ROL-03 solo ve existencias de bodegas que tenga ASIGNADAS (no toda la
        // sucursal); así dos bodegas de una misma sucursal no se filtran entre sí en la lectura.
        $user = $request->user();
        $stock = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->forOperatorWarehouses($user)
            ->when(
                $request->validated('warehouse_id'),
                fn ($q, $warehouseId) => $q->where('warehouse_id', $warehouseId)
            )
            ->when(
                $request->validated('product_id'),
                fn ($q, $productId) => $q->where('product_id', $productId)
            )
            // below_min=true: existencias por debajo del mínimo (scope del modelo).
            ->when(
                $request->boolean('below_min'),
                fn ($q) => $q->belowMin()
            )
            // search: por nombre o SKU del producto asociado.
            ->when(
                $request->validated('search'),
                fn ($q, $search) => $q->whereHas('product', fn ($p) => $p
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"))
            )
            ->orderBy($request->sortColumn('updated_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        // Adjunta el ÚLTIMO conteo físico por par (producto, bodega) de la página: la vista consolidada
        // muestra existencia + reservado + disponible JUNTO al último conteo/diferencia/estado de conciliación,
        // sin presentar el conteo como existencia actual (campos separados).
        $this->attachLatestCounts($stock->getCollection());

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

        $this->attachLatestCounts(collect([$stock]));

        return new StockLevelResource($stock);
    }

    /**
     * Adjunta a cada saldo su último conteo físico (par producto+bodega), en una sola consulta.
     * setRelation siempre se invoca (incluso con null) para que el recurso distinga "sin conteo aún".
     *
     * @param  Collection<int, StockLevel>  $levels
     */
    private function attachLatestCounts(Collection $levels): void
    {
        if ($levels->isEmpty()) {
            return;
        }

        $productIds   = $levels->pluck('product_id')->unique()->values()->all();
        $warehouseIds = $levels->pluck('warehouse_id')->unique()->values()->all();

        // Último por par: orden descendente y se toma el primero de cada grupo. El BusinessScope global
        // de PhysicalCount acota al negocio activo (lectura HTTP autenticada).
        $latestByPair = PhysicalCount::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (PhysicalCount $c): string => $c->product_id.':'.$c->warehouse_id)
            ->map(fn (Collection $group): PhysicalCount => $group->first());

        foreach ($levels as $level) {
            $key = $level->product_id.':'.$level->warehouse_id;
            $level->setRelation('latestCount', $latestByPair->get($key));
        }
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
