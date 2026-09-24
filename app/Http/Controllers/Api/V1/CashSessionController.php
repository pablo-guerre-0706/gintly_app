<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashSession\IndexCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CashSessionController extends Controller
{
    use AuthorizesRequests;

    // IndexCashSessionRequest valida/sanea filtros, orden y paginación (contrato MOD-06)
    // y autoriza viewAny. Alcance por rol: ROL-01/ROL-02 ven todas las sesiones del
    // negocio (y pueden filtrar por opened_by); ROL-03 ve EXCLUSIVAMENTE las suyas, y el
    // filtro opened_by NO puede ampliar ese alcance.
    public function index(IndexCashSessionRequest $request): AnonymousResourceCollection
    {
        $user    = $request->user();
        $isAdmin = $user->holdsAtLeast(RoleName::Admin);

        // Admin: opened_by opcional del request. ROL-03: forzado a sí mismo.
        $openedByScope = $isAdmin ? $request->validated('opened_by') : $user->id;

        $sessions = CashSession::query()
            ->with(['cashRegister', 'openedBy', 'closedBy'])
            ->when(
                $request->validated('status'),
                fn ($q, $status) => $q->where('status', $status),
            )
            ->when(
                $request->validated('cash_register_id'),
                fn ($q, $registerId) => $q->where('cash_register_id', $registerId),
            )
            ->when(
                $openedByScope,
                fn ($q, $openedBy) => $q->where('opened_by', $openedBy),
            )
            ->when(
                $request->fromDateTime(),
                fn ($q, $from) => $q->where('opened_at', '>=', $from),
            )
            ->when(
                $request->toDateTime(),
                fn ($q, $to) => $q->where('opened_at', '<=', $to),
            )
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return CashSessionResource::collection($sessions);
    }

    public function show(CashSession $cashSession): CashSessionResource
    {
        $this->authorize('view', $cashSession);

        return CashSessionResource::make(
            $cashSession->load(['cashRegister', 'openedBy', 'closedBy']),
        );
    }
}
