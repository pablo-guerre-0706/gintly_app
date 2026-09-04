<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashSession\CloseCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Services\Cash\CashService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class CloseCashSessionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CashService $cash)
    {
    }

    public function __invoke(CloseCashSessionRequest $request, CashSession $cashSession): CashSessionResource
    {
        $this->authorize('close', $cashSession); // autoría o Admin+

        $session = $this->cash->cerrar($cashSession, $request->validated(), $request->user());

        return CashSessionResource::make(
            $session->load(['cashRegister', 'openedBy', 'closedBy']),
        );
    }
}
