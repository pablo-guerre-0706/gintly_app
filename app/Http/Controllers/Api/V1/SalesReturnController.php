<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesReturn\IndexSalesReturnRequest;
use App\Http\Requests\Api\V1\SalesReturn\StoreSalesReturnRequest;
use App\Http\Resources\SalesReturnItemResource;
use App\Http\Resources\SalesReturnResource;
use App\Models\SalesReturn;
use App\Services\Returns\ReturnService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-10 · Devoluciones (capa HTTP delgada).
 *
 * Valida por FormRequest, autoriza por SalesReturnPolicy, delega la transacción atómica
 * (inventario, merma, CxC, caja, nota de crédito y acumulados) a ReturnService y responde
 * por Resources. Sin lógica de negocio, cálculo fiscal ni manipulación de inventario/caja.
 */
final class SalesReturnController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReturnService $returns)
    {
    }

    /** GET /sales-returns — devoluciones del negocio, filtradas y paginadas. */
    public function index(IndexSalesReturnRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesReturn::class);

        $salesReturns = SalesReturn::query()
            ->with(['user', 'invoice', 'customer', 'creditNote']) // Evita N+1.
            ->when(
                $request->filled('invoice_id'),
                fn ($query) => $query->where('invoice_id', $request->integer('invoice_id')),
            )
            ->when(
                $request->filled('customer_id'),
                fn ($query) => $query->where('customer_id', $request->integer('customer_id')),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->when(
                $request->fromDateTime(),
                fn ($query, $from) => $query->where('returned_at', '>=', $from),
            )
            ->when(
                $request->toDateTime(),
                fn ($query, $to) => $query->where('returned_at', '<=', $to),
            )
            ->orderBy($request->sortColumn('id'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return SalesReturnResource::collection($salesReturns);
    }

    /** POST /sales-returns — registrar una devolución (atómica en el servicio). */
    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        $this->authorize('create', SalesReturn::class);

        $salesReturn = $this->returns->registrar($request->validated());

        return SalesReturnResource::make(
            $salesReturn->load(['user', 'invoice', 'customer', 'creditNote.resolutions', 'items.product'])
        )->response()->setStatusCode(201);
    }

    /** GET /sales-returns/{salesReturn} — detalle de la devolución. */
    public function show(SalesReturn $salesReturn): SalesReturnResource
    {
        $this->authorize('view', $salesReturn);

        return SalesReturnResource::make(
            $salesReturn->load(['user', 'invoice', 'customer', 'creditNote.resolutions', 'items.product'])
        );
    }

    /** GET /sales-returns/{salesReturn}/items — líneas devueltas (sin N+1). */
    public function items(SalesReturn $salesReturn): AnonymousResourceCollection
    {
        $this->authorize('viewItems', $salesReturn);

        $items = $salesReturn->items()->with('product')->orderBy('id')->get();

        return SalesReturnItemResource::collection($items);
    }
}
