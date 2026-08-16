<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PhysicalCount\IndexPhysicalCountRequest;
use App\Http\Requests\Api\V1\PhysicalCount\StorePhysicalCountRequest;
use App\Http\Resources\PhysicalCountResource;
use App\Models\PhysicalCount;
use App\Services\Inventory\PhysicalCountService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PhysicalCountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PhysicalCountService $counts,
    ) {}

    public function index(IndexPhysicalCountRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PhysicalCount::class);

        $counts = PhysicalCount::query()
            ->with(['product', 'warehouse', 'user'])
            ->latest('counted_at')
            ->paginate($request->integer('per_page', 15));

        return PhysicalCountResource::collection($counts);
    }

    public function store(StorePhysicalCountRequest $request): JsonResponse
    {
        $this->authorize('create', PhysicalCount::class);

        // Contrato real: actor + primitivos desglosados. system_quantity/difference/counted_at los fija el Service/motor.
        $count = $this->counts->registrar(
            $request->user(),
            (int) $request->validated('warehouse_id'),
            (int) $request->validated('product_id'),
            (string) $request->validated('counted_quantity'),
            $request->validated('notes'),
        );

        return (new PhysicalCountResource($count->load(['product', 'warehouse', 'user'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PhysicalCount $physicalCount): PhysicalCountResource
    {
        $this->authorize('view', $physicalCount);

        return new PhysicalCountResource($physicalCount->load(['product', 'warehouse', 'user']));
    }
}
