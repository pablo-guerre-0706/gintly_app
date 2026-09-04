<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashSession\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Services\Cash\CashService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

final class OpenCashSessionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CashService $cash)
    {
    }

    public function __invoke(OpenCashSessionRequest $request): JsonResponse
    {
        $this->authorize('create', CashSession::class);

        $session = $this->cash->abrir($request->validated(), $request->user());

        return CashSessionResource::make($session->load(['cashRegister', 'openedBy']))
            ->response()
            ->setStatusCode(201);
    }
}
