<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PeriodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\DashboardKpiRequest;
use App\Http\Requests\Api\V1\Report\IndexKpiSnapshotRequest;
use App\Http\Requests\Api\V1\Report\RecalculateKpiRequest;
use App\Http\Resources\KpiSnapshotResource;
use App\Models\KpiSnapshot;
use App\Services\Report\KpiService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-12 · Instantáneas de KPI (capa HTTP delgada). Fórmulas, períodos en business.timezone,
 * atomicidad, idempotencia y candados viven en KpiService. business_id SIEMPRE de la sesión.
 */
final class KpiSnapshotController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly KpiService $kpis)
    {
    }

    /** GET /kpi-snapshots — instantáneas del negocio (caché recalculable), filtradas y paginadas. */
    public function index(IndexKpiSnapshotRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', KpiSnapshot::class);

        $snapshots = KpiSnapshot::query()
            ->when($request->filled('kpi_code'), fn ($q) => $q->where('kpi_code', $request->string('kpi_code')))
            ->when($request->filled('period_type'), fn ($q) => $q->where('period_type', $request->string('period_type')))
            ->when($request->filled('period_start'), fn ($q) => $q->whereDate('period_start', $request->date('period_start')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderBy($request->sortColumn('calculated_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return KpiSnapshotResource::collection($snapshots);
    }

    /** POST /kpi-snapshots/recalculate — recálculo manual forzado (ROL-01). Reemplaza la caché del período. */
    public function recalculate(RecalculateKpiRequest $request): AnonymousResourceCollection
    {
        $this->authorize('recalculate', KpiSnapshot::class);

        $businessId = (int) $request->user()->business_id; // Nunca del payload.
        $periodType = PeriodType::from($request->validated('period_type'));

        // Recálculo atómico e idempotente desde las fuentes (transacción/candados dentro del Service).
        $result = $this->kpis->calcular($businessId, $periodType, $request->input('reference_date'));

        return KpiSnapshotResource::collection(
            $this->kpis->dashboard($businessId, $periodType, $result['period']['start']),
        );
    }

    /** GET /dashboard/kpis — panel consolidado del negocio para el período vigente o indicado (ROL-01). */
    public function dashboard(DashboardKpiRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewDashboard', KpiSnapshot::class);

        $businessId = (int) $request->user()->business_id;
        $periodType = PeriodType::from($request->validated('period_type'));

        return KpiSnapshotResource::collection(
            $this->kpis->dashboard($businessId, $periodType, $request->input('period_start')),
        );
    }
}
