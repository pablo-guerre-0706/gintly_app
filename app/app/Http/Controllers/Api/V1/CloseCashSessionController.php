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

        $validated = $request->validated();

        // Alineamos las variables al orden exacto y tipos estrictos que exige el Service.
        // La clave validada es 'counted_denominations' (CloseCashSessionRequest); antes
        // se leía 'denominations' (inexistente), descartando el desglose del arqueo.
        $session = $this->cash->cerrar(
            $request->user(),
            $cashSession,
            (string) $validated['counted_amount'],
            (array) $validated['counted_denominations'],
            isset($validated['closing_notes']) ? (string) $validated['closing_notes'] : null
        );

        return CashSessionResource::make(
            $session->load(['cashRegister', 'openedBy', 'closedBy']),
        );
    }
}
