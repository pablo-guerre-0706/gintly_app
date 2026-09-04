<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CashSessionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashSession::class);

        $sessions = CashSession::query()
            ->with(['cashRegister', 'openedBy', 'closedBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('cash_register_id'), fn ($q) => $q->where('cash_register_id', $request->integer('cash_register_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

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
