<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class BranchController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Branch::class, 'branch');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // Branch usa BelongsToBusiness -> BusinessScope global aísla el tenant automáticamente (no requiere filtro manual).
        return BranchResource::collection(
            Branch::query()->orderByDesc('id')->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        // business_id lo auto-rellena BelongsToBusiness en 'creating'. D5: pertenencia de manager_user_id validada en el request.
        $branch = Branch::create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): Response
    {
        $branch->delete(); // asume SoftDeletes en Branch; si no lo tiene, sustituir por is_active=false.

        return response()->noContent();
    }
}
