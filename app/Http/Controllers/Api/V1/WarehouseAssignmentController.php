<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WarehouseAssignment\IndexWarehouseAssignmentRequest;
use App\Http\Requests\Api\V1\WarehouseAssignment\StoreWarehouseAssignmentRequest;
use App\Http\Resources\WarehouseAssignmentResource;
use App\Models\WarehouseAssignment;
use App\Services\Inventory\WarehouseAssignmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-03 · Asignaciones Bodega–Bodeguero. Capa HTTP delgada: valida en el FormRequest, autoriza por
 * WarehouseAssignmentPolicy y delega las invariantes a WarehouseAssignmentService.
 */
final class WarehouseAssignmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly WarehouseAssignmentService $assignments) {}

    /** GET /warehouse-assignments — ROL-01/ROL-02 todas (con filtros); ROL-03 solo las suyas. */
    public function index(IndexWarehouseAssignmentRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $isAdmin = $user->holdsAtLeast(RoleName::Admin);
        $userScope = $isAdmin ? $request->validated('user_id') : $user->id;

        $rows = WarehouseAssignment::query()
            ->with(['warehouse', 'user'])
            ->when(
                $request->validated('warehouse_id'),
                fn ($q, $warehouseId) => $q->where('warehouse_id', $warehouseId),
            )
            ->when(
                $userScope,
                fn ($q, $userId) => $q->where('user_id', $userId),
            )
            ->when(
                $request->has('active'),
                fn ($q) => $request->boolean('active') ? $q->whereNull('ended_at') : $q->whereNotNull('ended_at'),
            )
            ->orderBy($request->sortColumn('assigned_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return WarehouseAssignmentResource::collection($rows);
    }

    /** POST /warehouse-assignments — asigna un bodeguero a una bodega (M:N; 409 si el par ya está activo). */
    public function store(StoreWarehouseAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', WarehouseAssignment::class);

        $assignment = $this->assignments->assign(
            $request->user(),
            (int) $request->validated('warehouse_id'),
            (int) $request->validated('user_id'),
        );

        return WarehouseAssignmentResource::make($assignment->load(['warehouse', 'user']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /** DELETE /warehouse-assignments/{assignment} — finaliza (cierra la vigencia). */
    public function destroy(WarehouseAssignment $warehouseAssignment): WarehouseAssignmentResource
    {
        $this->authorize('delete', $warehouseAssignment);

        $finished = $this->assignments->finish(request()->user(), $warehouseAssignment);

        return WarehouseAssignmentResource::make($finished->load(['warehouse', 'user']));
    }
}
