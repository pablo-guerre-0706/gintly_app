<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\IndexBusinessGoalRequest;
use App\Http\Requests\Api\V1\Report\StoreBusinessGoalRequest;
use App\Http\Requests\Api\V1\Report\UpdateBusinessGoalRequest;
use App\Http\Resources\BusinessGoalResource;
use App\Models\BusinessGoal;
use App\Services\Report\GoalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-12 · Metas de negocio (capa HTTP delgada). La unicidad (global/sucursal), la traducción del
 * conflicto 1062 a 422 y la inmutabilidad de identidad viven en GoalService. business_id y created_by
 * NUNCA vienen del payload: los fijan BusinessScope y el Service desde la sesión.
 */
final class BusinessGoalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly GoalService $goals)
    {
    }

    /** GET /business-goals — metas del negocio, filtradas y paginadas (ROL-01/ROL-02). */
    public function index(IndexBusinessGoalRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BusinessGoal::class);

        $goals = BusinessGoal::query()
            ->when($request->filled('kpi_code'), fn ($q) => $q->where('kpi_code', $request->string('kpi_code')))
            ->when($request->filled('period_type'), fn ($q) => $q->where('period_type', $request->string('period_type')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderBy($request->sortColumn('period_start'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return BusinessGoalResource::collection($goals);
    }

    /** POST /business-goals — crea una meta (solo ROL-01). Conflicto de unicidad → 422 controlado. */
    public function store(StoreBusinessGoalRequest $request): JsonResponse
    {
        $this->authorize('create', BusinessGoal::class);

        $goal = $this->goals->crear($request->validated());

        return BusinessGoalResource::make($goal)->response()->setStatusCode(201);
    }

    /** PUT /business-goals/{businessGoal} — ajusta meta y/o fin de período (identidad inmutable, ROL-01). */
    public function update(UpdateBusinessGoalRequest $request, BusinessGoal $businessGoal): BusinessGoalResource
    {
        $this->authorize('update', $businessGoal);

        $goal = $this->goals->actualizar($businessGoal, $request->validated());

        return BusinessGoalResource::make($goal);
    }

    /** DELETE /business-goals/{businessGoal} — elimina una meta viva (ROL-01). */
    public function destroy(BusinessGoal $businessGoal): JsonResponse
    {
        $this->authorize('delete', $businessGoal);

        $this->goals->eliminar($businessGoal);

        return response()->json(null, 204);
    }
}
