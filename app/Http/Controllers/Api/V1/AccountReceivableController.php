<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Receivables\IndexAccountReceivableRequest;
use App\Http\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AccountReceivableController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexAccountReceivableRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccountReceivable::class);

        $receivables = AccountReceivable::query()
            ->with(['customer', 'invoice'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return AccountReceivableResource::collection($receivables);
    }

    public function show(AccountReceivable $accountReceivable): AccountReceivableResource
    {
        $this->authorize('view', $accountReceivable);

        return AccountReceivableResource::make(
            $accountReceivable->load(['customer', 'invoice']),
        );
    }
}
