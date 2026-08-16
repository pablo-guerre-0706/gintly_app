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

        $validated = $request->validated();

        $payables = AccountPayable::query()
            ->with(['supplier', 'purchaseOrder'])
            // Filtro overdue (IndexAccountPayableRequest): vencidas y aún con saldo (independiente del enum de estado).
            ->when($validated['overdue'] ?? false, fn ($q) => $q
                ->whereDate('due_date', '<', now())
                ->whereColumn('paid_amount', '<', 'total_amount'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AccountPayableResource::collection($payables);
    }

    public function show(AccountPayable $accountPayable): AccountPayableResource
    {
        $this->authorize('view', $accountPayable);

        return new AccountPayableResource($accountPayable->load(['supplier', 'purchaseOrder', 'goodsReceipt']));
    }
}
