<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DispatchExceedsBalanceException extends Exception
{
    public function __construct(
        private readonly string $pending = '0.000',
        private readonly string $requested = '0.000',
    ) {
        parent::__construct('El retiro excede el saldo pendiente de entrega de la línea.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message'   => $this->getMessage(),
            'error'     => 'DISPATCH_EXCEEDS_BALANCE',
            'pending'   => $this->pending,
            'requested' => $this->requested,
        ], 422);
    }
}
