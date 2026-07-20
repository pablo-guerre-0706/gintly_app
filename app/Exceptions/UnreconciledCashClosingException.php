<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class UnreconciledCashClosingException extends Exception
{
    public function __construct(private readonly string $difference = '0.00')
    {
        parent::__construct('Cierre con descuadre: la sesión quedó marcada como descuadrada y requiere validación administrativa.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message'    => $this->getMessage(),
            'error'      => 'UNRECONCILED_CASH_CLOSING',
            'difference' => $this->difference,
        ], 422);
    }
}
