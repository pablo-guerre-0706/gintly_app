<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashMovement\StoreCashMovementRequest;
use App\Http\Resources\CashMovementResource;
use App\Models\CashMovement;
use App\Services\Cash\CashService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CashMovementController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CashService $cash)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashMovement::class);

        $movements = CashMovement::query()
            ->with(['cashSession', 'user', 'authorizedBy'])
            ->when($request->filled('cash_session_id'), fn ($q) => $q->where('cash_session_id', $request->integer('cash_session_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return CashMovementResource::collection($movements);
    }

    public function store(StoreCashMovementRequest $request): JsonResponse
    {
        $this->authorize('create', CashMovement::class);

        $movement = $this->cash->registrarMovimiento($request->validated(), $request->user());

        return CashMovementResource::make($movement->load(['cashSession', 'user', 'authorizedBy']))
            ->response()
            ->setStatusCode(201);
    }
}
