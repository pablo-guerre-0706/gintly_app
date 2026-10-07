<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashSession\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Services\Billing\PlanLimits;
use App\Services\Cash\CashService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

final class OpenCashSessionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CashService $cash)
    {
    }

    public function __invoke(OpenCashSessionRequest $request, PlanLimits $limits): JsonResponse
    {
        $this->authorize('create', CashSession::class);

        $validated = $request->validated();

        // Límite de cajas operando simultáneamente del plan, verificado y aplicado bajo lock (apertura incluida).
        $session = $limits->guardCashSessionOpen(
            (int) $request->user()->business_id,
            fn () => $this->cash->abrir(
                $request->user(),
                (int) $validated['cash_register_id'],
                (string) $validated['opening_amount'],
                isset($validated['opening_amount_usd']) ? (string) $validated['opening_amount_usd'] : '0.00',
            ),
        );

        return CashSessionResource::make($session->load(['cashRegister', 'openedBy']))
            ->response()
            ->setStatusCode(201);
    }
}
