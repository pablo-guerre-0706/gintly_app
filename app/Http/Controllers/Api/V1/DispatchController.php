<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dispatch\IndexDispatchRequest;
use App\Http\Requests\Api\V1\Dispatch\RevertDispatchRequest;
use App\Http\Requests\Api\V1\Dispatch\StoreDispatchRequest;
use App\Http\Resources\DispatchItemResource;
use App\Http\Resources\DispatchResource;
use App\Models\Dispatch;
use App\Services\Dispatch\DispatchService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-09 · Entregas y Retiros de Mercancía (capa HTTP delgada).
 *
 * Valida por FormRequest, autoriza por DispatchPolicy, delega la transacción y las
 * reglas de inventario/concurrencia a DispatchService y responde por Resources.
 * No contiene lógica de inventario ni consultas complejas.
 */
final class DispatchController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly DispatchService $dispatches)
    {
    }

    /** GET /dispatches — cartera de retiros del negocio (RF-09), filtrada y paginada. */
    public function index(IndexDispatchRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Dispatch::class);

        $actor = $request->user();

        $dispatches = Dispatch::query()
            ->with(['user', 'warehouse', 'invoice']) // Evita N+1 en el listado.
            ->when(
                $request->filled('invoice_id'),
                fn ($query) => $query->where('invoice_id', $request->integer('invoice_id')),
            )
            ->when(
                $request->filled('warehouse_id'),
                fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->when(
                $request->fromDateTime(),
                fn ($query, $from) => $query->where('dispatched_at', '>=', $from),
            )
            ->when(
                $request->toDateTime(),
                fn ($query, $to) => $query->where('dispatched_at', '<=', $to),
            )
            // Alcance de sucursal: ROL-03 con sucursal asignada solo ve la suya.
            ->when(
                ! $actor->holdsAtLeast(RoleName::Admin) && $actor->branch_id !== null,
                fn ($query) => $query->where('branch_id', $actor->branch_id),
            )
            ->orderBy($request->sortColumn('id'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return DispatchResource::collection($dispatches);
    }

    /** POST /dispatches — registrar un retiro total o parcial (atómico en el servicio). */
    public function store(StoreDispatchRequest $request): JsonResponse
    {
        $this->authorize('create', Dispatch::class);

        $dispatch = $this->dispatches->registrar($request->user(), $request->validated());

        return DispatchResource::make($dispatch->load(['user', 'warehouse', 'invoice', 'items.product']))
            ->response()
            ->setStatusCode(201);
    }

    /** GET /dispatches/{dispatch} — detalle del retiro. */
    public function show(Dispatch $dispatch): DispatchResource
    {
        $this->authorize('view', $dispatch);

        return DispatchResource::make(
            $dispatch->load(['user', 'revertedBy', 'warehouse', 'invoice', 'items.product'])
        );
    }

    /** GET /dispatches/{dispatch}/items — líneas del retiro (sin N+1). */
    public function items(Dispatch $dispatch): AnonymousResourceCollection
    {
        $this->authorize('viewItems', $dispatch);

        $items = $dispatch->items()
            ->with(['product', 'saleItem'])
            ->orderBy('id')
            ->get();

        return DispatchItemResource::collection($items);
    }

    /** POST /dispatches/{dispatch}/revert — reversión con reingreso y re-reserva (ROL-02). */
    public function revert(RevertDispatchRequest $request, Dispatch $dispatch): DispatchResource
    {
        $this->authorize('revert', $dispatch);

        $reverted = $this->dispatches->revertir($dispatch, $request->validated('revert_reason'));

        return DispatchResource::make(
            $reverted->load(['user', 'revertedBy', 'warehouse', 'invoice', 'items.product'])
        );
    }
}
