<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReconciliationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Anomaly\IndexReconciliationRunRequest;
use App\Http\Requests\Api\V1\Anomaly\StoreReconciliationRunRequest;
use App\Http\Resources\ReconciliationRunResource;
use App\Models\ReconciliationRun;
use App\Services\Anomaly\ReconciliationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-11 · Corridas de conciliación (capa HTTP delgada). La auditoría solo-lectura de los
 * registros operativos y la generación de anomalías/eventos viven en ReconciliationService.
 */
final class ReconciliationRunController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReconciliationService $reconciliation)
    {
    }

    /** GET /reconciliation-runs — corridas del negocio, filtradas y paginadas. */
    public function index(IndexReconciliationRunRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ReconciliationRun::class);

        $runs = ReconciliationRun::query()
            ->when(
                $request->filled('scope'),
                fn ($query) => $query->where('scope', $request->string('scope')),
            )
            ->when(
                $request->filled('run_type'),
                fn ($query) => $query->where('run_type', $request->string('run_type')),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->when(
                $request->fromDateTime(),
                fn ($query, $from) => $query->where('started_at', '>=', $from),
            )
            ->when(
                $request->toDateTime(),
                fn ($query, $to) => $query->where('started_at', '<=', $to),
            )
            ->orderBy($request->sortColumn('id'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return ReconciliationRunResource::collection($runs);
    }

    /** POST /reconciliation-runs — ejecuta una conciliación MANUAL (ROL-02+). */
    public function store(StoreReconciliationRunRequest $request): JsonResponse
    {
        $this->authorize('create', ReconciliationRun::class);

        $actor = $request->user();

        // business_id y triggered_by SIEMPRE de la sesión, nunca del payload.
        $run = $this->reconciliation->conciliar(
            businessId: (int) $actor->business_id,
            scope: ReconciliationScope::from($request->validated('scope')),
            branchId: $request->integer('branch_id') ?: null,
            runType: 'manual',
            triggeredBy: (int) $actor->id,
        );

        return ReconciliationRunResource::make($run->load('anomalies'))
            ->response()
            ->setStatusCode(201);
    }

    /** GET /reconciliation-runs/{reconciliationRun} — detalle con sus anomalías. */
    public function show(ReconciliationRun $reconciliationRun): ReconciliationRunResource
    {
        $this->authorize('view', $reconciliationRun);

        return ReconciliationRunResource::make($reconciliationRun->load('anomalies.rule'));
    }
}
