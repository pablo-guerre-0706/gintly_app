<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\RestrictDeleteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\IndexBranchRequest;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class BranchController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Branch::class, 'branch');
    }

    public function index(IndexBranchRequest $request): AnonymousResourceCollection
    {
        // Branch usa BelongsToBusiness -> BusinessScope global aísla el tenant automáticamente (no requiere filtro manual).
        // IndexBranchRequest valida y sanea filtros, orden y paginación (contrato MOD-01).
        $query = Branch::query();

        // Borrado lógico: por defecto solo activas; `with`/`only` según el filtro.
        $trashed = $request->validated('trashed');
        if ($trashed === 'with') {
            $query->withTrashed();
        } elseif ($trashed === 'only') {
            $query->onlyTrashed();
        }

        $query
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active'))
            )
            ->when(
                $request->validated('manager_user_id'),
                fn ($q, $managerId) => $q->where('manager_user_id', $managerId)
            )
            ->when(
                $request->validated('search'),
                fn ($q, $search) => $q->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%"))
            )
            ->when(
                $request->fromDateTime(),
                fn ($q, $from) => $q->where('created_at', '>=', $from)
            )
            ->when(
                $request->toDateTime(),
                fn ($q, $to) => $q->where('created_at', '<=', $to)
            );

        return BranchResource::collection(
            $query
                ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
                ->paginate($request->perPage()),
        );
    }

    public function store(StoreBranchRequest $request, PlanLimits $limits): JsonResponse
    {
        // business_id lo auto-rellena BelongsToBusiness en 'creating'. D5: pertenencia de manager_user_id validada en el request.
        // Límite de sucursales activas del plan, verificado y aplicado bajo lock (sin carrera concurrente).
        $branch = $limits->guardBranchActivation(
            (int) $request->user()->business_id,
            fn () => Branch::create($request->validated()),
        );

        return (new BranchResource($branch))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Branch $branch, PlanLimits $limits): BranchResource
    {
        $data = $request->validated();

        // Reactivar una sucursal (is_active false→true) cuenta como ALTA frente al límite del plan: se verifica
        // y aplica bajo el MISMO lock por negocio que la creación (sin carrera con altas concurrentes). Las demás
        // actualizaciones (renombrar, etc.) no tocan el cupo.
        $reactivating = array_key_exists('is_active', $data)
            && (bool) $data['is_active'] === true
            && ! (bool) $branch->is_active;

        if ($reactivating) {
            $limits->guardBranchActivation(
                (int) $request->user()->business_id,
                function () use ($branch, $data): Branch {
                    $branch->update($data);

                    return $branch;
                },
            );
        } else {
            $branch->update($data);
        }

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): Response
    {
        // ERR-02B (409): una sucursal con bodegas, cajas o usuarios vigentes no
        // se da de baja. El borrado es lógico, así que la guarda es de dominio
        // (las FK RESTRICT del motor no actúan sobre un UPDATE de soft-delete).
        if ($branch->hasOperationalDependents()) {
            throw RestrictDeleteException::make('la sucursal', 'bodegas, cajas o usuarios');
        }

        $branch->delete();

        return response()->noContent();
    }
}
