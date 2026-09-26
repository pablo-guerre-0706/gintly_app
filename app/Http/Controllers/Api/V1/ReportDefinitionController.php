<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\IndexReportDefinitionRequest;
use App\Http\Requests\Api\V1\Report\StoreReportDefinitionRequest;
use App\Http\Requests\Api\V1\Report\UpdateReportDefinitionRequest;
use App\Http\Resources\ReportDefinitionResource;
use App\Models\ReportDefinition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-12 · Definiciones de reporte reutilizables (capa HTTP delgada). Los filtros son estructura
 * JSON validada (jamás SQL libre). business_id (BusinessScope) y user_id se fijan desde la sesión.
 * is_scheduled/schedule_cron se persisten INERTES: el motor de envío programado es Fase 2.
 */
final class ReportDefinitionController extends Controller
{
    use AuthorizesRequests;

    /** GET /report-definitions — definiciones del negocio, filtradas y paginadas. */
    public function index(IndexReportDefinitionRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ReportDefinition::class);

        $definitions = ReportDefinition::query()
            ->when($request->filled('report_type'), fn ($q) => $q->where('report_type', $request->string('report_type')))
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return ReportDefinitionResource::collection($definitions);
    }

    /** POST /report-definitions — crea una definición reutilizable (user_id de la sesión). */
    public function store(StoreReportDefinitionRequest $request): JsonResponse
    {
        $this->authorize('create', ReportDefinition::class);

        $definition = new ReportDefinition($request->validated());
        $definition->user_id = (int) $request->user()->id; // No-repudio; nunca del payload.
        $definition->save();                                // business_id vía BusinessScope trait.

        return ReportDefinitionResource::make($definition->refresh())->response()->setStatusCode(201);
    }

    /** PUT /report-definitions/{reportDefinition} — actualiza una definición del negocio. */
    public function update(UpdateReportDefinitionRequest $request, ReportDefinition $reportDefinition): ReportDefinitionResource
    {
        $this->authorize('update', $reportDefinition);

        $reportDefinition->update($request->validated()); // user_id fuera de $fillable: inmutable.

        return ReportDefinitionResource::make($reportDefinition->refresh());
    }

    /** DELETE /report-definitions/{reportDefinition} — elimina una definición del negocio. */
    public function destroy(ReportDefinition $reportDefinition): JsonResponse
    {
        $this->authorize('delete', $reportDefinition);

        $reportDefinition->delete();

        return response()->json(null, 204);
    }
}
