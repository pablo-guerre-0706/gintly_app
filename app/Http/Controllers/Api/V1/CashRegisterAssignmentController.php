<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashRegisterAssignment\IndexCashRegisterAssignmentRequest;
use App\Http\Requests\Api\V1\CashRegisterAssignment\StoreCashRegisterAssignmentRequest;
use App\Http\Resources\CashRegisterAssignmentResource;
use App\Models\CashRegisterAssignment;
use App\Services\Cash\CashRegisterAssignmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-06 · Asignaciones Caja–Cajero. Capa HTTP delgada: valida en el FormRequest, autoriza por
 * CashRegisterAssignmentPolicy y delega las invariantes a CashRegisterAssignmentService.
 */
final class CashRegisterAssignmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CashRegisterAssignmentService $assignments) {}

    /** GET /cash-register-assignments — ROL-01/ROL-02 todas (con filtros); ROL-03 solo las suyas. */
    public function index(IndexCashRegisterAssignmentRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $isAdmin = $user->holdsAtLeast(RoleName::Admin);

        // ROL-03: acotado a sí mismo; el filtro user_id NO amplía su alcance.
        $userScope = $isAdmin ? $request->validated('user_id') : $user->id;

        $rows = CashRegisterAssignment::query()
            ->with(['cashRegister', 'user'])
            ->when(
                $request->validated('cash_register_id'),
                fn ($q, $registerId) => $q->where('cash_register_id', $registerId),
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

        return CashRegisterAssignmentResource::collection($rows);
    }

    /** POST /cash-register-assignments — asigna un cajero a una caja (estricto; 409 si hay conflicto). */
    public function store(StoreCashRegisterAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', CashRegisterAssignment::class);

        $assignment = $this->assignments->assign(
            $request->user(),
            (int) $request->validated('cash_register_id'),
            (int) $request->validated('user_id'),
        );

        return CashRegisterAssignmentResource::make($assignment->load(['cashRegister', 'user']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /** DELETE /cash-register-assignments/{assignment} — finaliza (cierra la vigencia). */
    public function destroy(CashRegisterAssignment $cashRegisterAssignment): CashRegisterAssignmentResource
    {
        $this->authorize('delete', $cashRegisterAssignment);

        $finished = $this->assignments->finish(request()->user(), $cashRegisterAssignment);

        return CashRegisterAssignmentResource::make($finished->load(['cashRegister', 'user']));
    }
}
