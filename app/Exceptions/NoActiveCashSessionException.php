<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class NoActiveCashSessionException extends Exception
{
    public function __construct(string $message = 'No existe una sesión de caja activa para registrar la operación.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'NO_ACTIVE_CASH_SESSION'], 409);
    }
}
