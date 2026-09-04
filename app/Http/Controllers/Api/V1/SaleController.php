<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sale\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SaleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly SaleService $sales)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        $sales = Sale::query()
            ->with(['customer', 'branch', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return SaleResource::collection($sales);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        // 1. Obtenemos el array de datos validados
        $validated = $request->validated();

        // 2. CORRECCIÓN: Desglosamos y tipamos los datos según la firma de SaleService::abrir()
        $sale = $this->sales->abrir(
            $request->user(),                               // 1º User $actor
            (int) $validated['branch_id'],                  // 2º int $branchId
            (int) $validated['customer_id'],                // 3º int $customerId
            $validated['table_reference'] ?? null,          // 4º ?string $tableReference
            $validated['notes'] ?? null                     // 5º ?string $notes
        );

        return SaleResource::make($sale->load(['customer', 'branch', 'user']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        return SaleResource::make($sale->load(['customer', 'branch', 'user', 'items']));
    }
}
