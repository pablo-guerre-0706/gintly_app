<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashRegister\IndexCashRegisterRequest;
use App\Http\Requests\Api\V1\CashRegister\StoreCashRegisterRequest;
use App\Http\Requests\Api\V1\CashRegister\UpdateCashRegisterRequest;
use App\Http\Resources\CashRegisterResource;
use App\Models\CashRegister;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CashRegisterController extends Controller
{
    use AuthorizesRequests;

    // IndexCashRegisterRequest valida filtros (branch_id, is_active), autoriza viewAny
    // y sanea orden/paginación. Alcance por rol: ROL-01/ROL-02 administran las cajas del
    // negocio (con filtros del request); ROL-03 SOLO ve cajas ACTIVAS de su propia
    // sucursal (sin sucursal asignada → ninguna), acorde a lo que puede operar.
    public function index(IndexCashRegisterRequest $request): AnonymousResourceCollection
    {
        $user  = $request->user();
        $query = CashRegister::query()->with('branch');

        if ($user->holdsAtLeast(RoleName::Admin)) {
            $query
                ->when(
                    $request->validated('branch_id'),
                    fn ($q, $branchId) => $q->where('branch_id', $branchId),
                )
                ->when(
                    $request->has('is_active'),
                    fn ($q) => $q->where('is_active', $request->boolean('is_active')),
                );
        } else {
            // branch_id ?? 0 → si el operador no tiene sucursal, no ve ninguna caja.
            $query->where('is_active', true)
                ->where('branch_id', $user->branch_id ?? 0);
        }

        $registers = $query
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

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
