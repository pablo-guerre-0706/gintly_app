<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UnreconciledCashClosingException extends RuntimeException
{
    public function __construct(
        public readonly CashSession $session,
        string $message = 'El cierre presenta un descuadre entre el efectivo contado y el esperado.',
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        // La sesión se persistió (commit) antes de lanzar: es evidencia real, no un rollback.
        $this->session->loadMissing(['cashRegister', 'openedBy', 'closedBy']);

        return response()->json([
            'error'   => 'CASH_CLOSING_UNRECONCILED',
            'message' => $this->getMessage(),
            'data'    => CashSessionResource::make($this->session),
        ], 422);
    }
}
