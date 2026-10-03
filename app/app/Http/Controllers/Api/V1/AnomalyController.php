<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Anomaly\IndexAnomalyRequest;
use App\Http\Requests\Api\V1\Anomaly\JustifyAnomalyRequest;
use App\Http\Requests\Api\V1\Anomaly\ResolveAnomalyRequest;
use App\Http\Resources\AnomalyEventResource;
use App\Http\Resources\AnomalyResource;
use App\Models\Anomaly;
use App\Services\Anomaly\AnomalyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-11 · Anomalías (capa HTTP delgada). La máquina de estados, la resolución del causante
 * (BR-01) y la bitácora append-only viven en AnomalyService. El controlador valida, autoriza,
 * delega y transforma.
 */
final class AnomalyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly AnomalyService $anomalies)
    {
    }

    /** GET /anomalies — cartera de anomalías del negocio, filtrada, ordenada y paginada. */
    public function index(IndexAnomalyRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Anomaly::class);

        $anomalies = Anomaly::query()
            ->with('rule') // Evita N+1 (code/severidad de la regla).
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->when(
                $request->filled('severity'),
                fn ($query) => $query->where('severity', $request->string('severity')),
            )
            ->when(
                $request->filled('rule_code'),
                fn ($query) => $query->whereHas('rule', fn ($r) => $r->where('code', $request->string('rule_code'))),
            )
            ->when(
                $request->filled('branch_id'),
                fn ($query) => $query->where('branch_id', $request->integer('branch_id')),
            )
            ->when(
                $request->filled('source_type'),
                fn ($query) => $query->where('source_type', $request->string('source_type')),
            )
            ->when(
                $request->fromDateTime(),
                fn ($query, $from) => $query->where('detected_at', '>=', $from),
            )
            ->when(
                $request->toDateTime(),
                fn ($query, $to) => $query->where('detected_at', '<=', $to),
            )
            ->orderBy($request->sortColumn('detected_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return AnomalyResource::collection($anomalies);
    }

    /** GET /anomalies/{anomaly} — detalle con su regla y bitácora. */
    public function show(Anomaly $anomaly): AnomalyResource
    {
        $this->authorize('view', $anomaly);

        return AnomalyResource::make($anomaly->load(['rule', 'events.user']));
    }

    /** GET /anomalies/{anomaly}/events — bitácora inmutable de transiciones (append-only). */
    public function events(Anomaly $anomaly): AnonymousResourceCollection
    {
        $this->authorize('viewEvents', $anomaly);

        return AnomalyEventResource::collection($anomaly->events()->with('user')->get());
    }

    /** POST /anomalies/{anomaly}/justify — justificación (ROL-02). BR-01 lo refina el servicio. */
    public function justify(JustifyAnomalyRequest $request, Anomaly $anomaly): AnomalyResource
    {
        $this->authorize('justify', $anomaly);

        $justified = $this->anomalies->justificar($anomaly, $request->validated('reason'));

        return AnomalyResource::make($justified->load(['rule', 'events.user']));
    }

    /** POST /anomalies/{anomaly}/resolve — resolución (ROL-01). */
    public function resolve(ResolveAnomalyRequest $request, Anomaly $anomaly): AnomalyResource
    {
        $this->authorize('resolve', $anomaly);

        $resolved = $this->anomalies->resolver($anomaly, $request->validated('comment'));

        return AnomalyResource::make($resolved->load(['rule', 'events.user']));
    }
}
