<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AccountPayable\IndexAccountPayableRequest;
use App\Http\Resources\AccountPayableResource;
use App\Models\AccountPayable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AccountPayableController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexAccountPayableRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccountPayable::class);

        // IndexAccountPayableRequest valida filtros/orden/paginación (contrato MOD-04).
        $payables = AccountPayable::query()
            ->with(['supplier', 'purchaseOrder'])
            ->when(
                $request->validated('supplier_id'),
                fn ($q, $supplierId) => $q->where('supplier_id', $supplierId)
            )
            ->when(
                $request->validated('status'),
                fn ($q, $status) => $q->where('status', $status)
            )
            // Filtro overdue: vencidas y aún con saldo (independiente del enum de estado).
            ->when($request->boolean('overdue'), fn ($q) => $q
                ->whereDate('due_date', '<', now())
                ->whereColumn('paid_amount', '<', 'total_amount'))
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return AccountPayableResource::collection($payables);
    }

    public function show(AccountPayable $accountPayable): AccountPayableResource
    {
        $this->authorize('view', $accountPayable);

        return new AccountPayableResource($accountPayable->load(['supplier', 'purchaseOrder', 'goodsReceipt']));
    }
}
