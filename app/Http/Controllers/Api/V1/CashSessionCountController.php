<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CashSession\StoreCashCountRequest;
use App\Http\Resources\CashCountResource;
use App\Models\CashSession;
use App\Services\Cash\CashService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * MOD-06 · Arqueo ciego INDEPENDIENTE de una sesión abierta (RF-06-04). Capa HTTP delgada: valida en
 * StoreCashCountRequest, autoriza por CashSessionPolicy ('count'/'view') y delega a CashService.
 */
final class CashSessionCountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CashService $cash) {}

    /** POST /cash-sessions/{cashSession}/counts — registra un arqueo (no cierra la sesión). */
    public function store(StoreCashCountRequest $request, CashSession $cashSession): JsonResponse
    {
        // Autorización resuelta en StoreCashCountRequest::authorize() (CashSessionPolicy::count).
        $count = $this->cash->registrarArqueo(
            $request->user(),
            $cashSession,
            (string) $request->validated('counted_amount'),
            $request->validated('counted_denominations'),
            $request->validated('counted_amount_usd') !== null ? (string) $request->validated('counted_amount_usd') : '0.00',
            (array) ($request->validated('counted_denominations_usd') ?? []),
        );

        return CashCountResource::make($count)->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /** GET /cash-sessions/{cashSession}/counts — historial de arqueos de la sesión (más reciente primero). */
    public function index(CashSession $cashSession): AnonymousResourceCollection
    {
        $this->authorize('view', $cashSession); // mismo alcance que ver la sesión (propiedad/sucursal/negocio).

        $counts = $cashSession->counts()
            ->with('user')
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->get();

        return CashCountResource::collection($counts);
    }
}
