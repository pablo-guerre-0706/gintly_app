<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\ReportDefinition;
use App\Services\Report\ReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * MOD-12 · Reportería consolidada (capa HTTP delgada). Rangos en business.timezone, agregación
 * en SQL, comparación con el período equivalente anterior y trazabilidad viven en ReportService.
 * SOLO LECTURA: no muta datos operativos. business_id SIEMPRE de la sesión. El tipo se valida
 * contra el allowlist ReportType (nunca tabla/vista/columna arbitraria del cliente).
 */
final class ReportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ReportService $reports)
    {
    }

    /** GET /reports/{type} — reporte de ventas/cartera/inventario/caja/consolidado (ROL-01). */
    public function show(ReportRequest $request, string $type): ReportResource
    {
        $this->authorize('generateReport', ReportDefinition::class);

        // Allowlist estricto: un tipo desconocido no revela nada (404), no intenta SQL arbitrario.
        $reportType = ReportType::tryFrom($type);
        abort_if($reportType === null, 404);

        $report = $this->reports->generar(
            businessId: (int) $request->user()->business_id,
            type: $reportType,
            from: $request->input('from'),
            to: $request->input('to'),
            branchId: $request->integer('branch_id') ?: null,
        );

        return ReportResource::make($report);
    }
}
