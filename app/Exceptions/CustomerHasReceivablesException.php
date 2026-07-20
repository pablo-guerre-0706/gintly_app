<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CustomerHasReceivablesException extends Exception
{
    public function __construct(string $message = 'El cliente tiene cuentas por cobrar pendientes: no puede desactivarse.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'CUSTOMER_HAS_RECEIVABLES'], 422);
    }
}
