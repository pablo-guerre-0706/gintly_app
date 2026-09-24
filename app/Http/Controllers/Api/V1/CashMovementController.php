<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashMovementType;
use App\Enums\CashMovementCategory;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashMovement\IndexCashMovementRequest;
use App\Http\Requests\Api\V1\CashMovement\StoreCashMovementRequest;
use App\Http\Resources\CashMovementResource;
use App\Models\CashMovement;
use App\Services\Cash\CashService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CashMovementController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CashService $cash)
    {
    }

    // IndexCashMovementRequest valida enums (type/category/payment_method), autoriza
    // viewAny y sanea orden/paginación. Antes con Request plano faltaban el filtro
    // payment_method y el orden del contrato.
    public function index(IndexCashMovementRequest $request): AnonymousResourceCollection
    {
        $movements = CashMovement::query()
            ->with(['cashSession', 'user', 'authorizedBy'])
            ->when(
                $request->validated('cash_session_id'),
                fn ($q, $sessionId) => $q->where('cash_session_id', $sessionId),
            )
            ->when(
                $request->validated('type'),
                fn ($q, $type) => $q->where('type', $type),
            )
            ->when(
                $request->validated('category'),
                fn ($q, $category) => $q->where('category', $category),
            )
            ->when(
                $request->validated('payment_method'),
                fn ($q, $method) => $q->where('payment_method', $method),
            )
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return CashMovementResource::collection($movements);
    }

    public function store(StoreCashMovementRequest $request): JsonResponse
    {
        $this->authorize('create', CashMovement::class);

        $validated = $request->validated();

        // Mapeamos y casteamos los datos para cumplir con la firma estricta del Service
        $movement = $this->cash->registrarMovimiento(
            $request->user(),
            (int) $validated['cash_session_id'],
            CashMovementType::from($validated['type']),
            CashMovementCategory::from($validated['category']),
            PaymentMethod::from($validated['payment_method']),
            (string) $validated['amount'],
            isset($validated['authorized_by']) ? (int) $validated['authorized_by'] : null,
            $validated['description'] ?? null
        );

        return CashMovementResource::make($movement->load(['cashSession', 'user', 'authorizedBy']))
            ->response()
            ->setStatusCode(201);
    }
}
