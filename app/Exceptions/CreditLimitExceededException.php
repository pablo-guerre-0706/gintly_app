<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CreditLimitExceededException extends Exception
{
    public function __construct(
        private readonly string $exposure = '0.00',
        private readonly string $limit = '0.00',
    ) {
        parent::__construct('La venta a crédito excede el límite autorizado del cliente. Requiere autorización de ROL-01.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message'  => $this->getMessage(),
            'error'    => 'CREDIT_LIMIT_EXCEEDED',
            'exposure' => $this->exposure,
            'limit'    => $this->limit,
        ], 422);
    }
}
