<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashRegister\StoreCashRegisterRequest;
use App\Http\Requests\Api\V1\CashRegister\UpdateCashRegisterRequest;
use App\Http\Resources\CashRegisterResource;
use App\Models\CashRegister;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CashRegisterController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashRegister::class);

        $registers = CashRegister::query()
            ->with('branch')
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->boolean('only_active'), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return CashRegisterResource::collection($registers);
    }

    public function store(StoreCashRegisterRequest $request): JsonResponse
    {
        $this->authorize('create', CashRegister::class);

        $register = CashRegister::create($request->validated()); // business_id: auto-fill

        return CashRegisterResource::make($register->load('branch'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CashRegister $cashRegister): CashRegisterResource
    {
        $this->authorize('view', $cashRegister);

        return CashRegisterResource::make($cashRegister->load('branch'));
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $cashRegister): CashRegisterResource
    {
        $this->authorize('update', $cashRegister);

        $cashRegister->update($request->validated());

        return CashRegisterResource::make($cashRegister->load('branch'));
    }

    public function destroy(CashRegister $cashRegister): Response
    {
        $this->authorize('delete', $cashRegister);

        $cashRegister->delete(); // soft-delete

        return response()->noContent();
    }
}
