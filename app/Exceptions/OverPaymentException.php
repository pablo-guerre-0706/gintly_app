<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class OverpaymentException extends Exception
{
    public function __construct(string $message = 'El abono excede el saldo pendiente de la cuenta.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'OVERPAYMENT'], 422);
    }
}
