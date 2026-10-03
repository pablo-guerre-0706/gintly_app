<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashMovementResource;
use App\Models\CashSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CashSessionMovementsController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, CashSession $cashSession): AnonymousResourceCollection
    {
        $this->authorize('view', $cashSession);

        $movements = $cashSession->movements()
            ->with(['user', 'authorizedBy'])
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->input('payment_method')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return CashMovementResource::collection($movements);
    }
}
