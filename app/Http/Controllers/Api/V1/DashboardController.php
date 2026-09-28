<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 7 · Dashboards agregados por rol sobre datos reales.
 *  - admin (ROL-02+): pendientes operativos de todo el negocio.
 *  - operative (ROL-03+): secciones según los perfiles del usuario, acotadas a su sucursal.
 * El dashboard de KPIs de ROL-01 es GET /dashboard/kpis (MOD-12) — ruta canónica ya existente.
 * Controlador delgado: la agregación vive en DashboardService; la autoridad por recurso, en las Policies.
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    /** GET /dashboard/admin — ROL-02+ (administrativo, negocio completo). */
    public function admin(Request $request): JsonResponse
    {
        abort_unless($request->user()->holdsAtLeast(RoleName::Admin), 403, 'Requiere rol administrativo.');

        return response()->json(['data' => $this->dashboard->admin()]);
    }

    /** GET /dashboard/operative — ROL-03+ (operativo, por perfiles y sucursal). */
    public function operative(Request $request): JsonResponse
    {
        abort_unless($request->user()->holdsAtLeast(RoleName::Operator), 403, 'Requiere rol operativo.');

        return response()->json(['data' => $this->dashboard->operative($request->user())]);
    }
}
