<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PurchaseMatchException extends Exception
{
    public function __construct(
        string $message = 'Discrepancia en 3-Way Match: ingreso detenido y CxP congelada. Requiere resolución de ROL-01.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'PURCHASE_MATCH'], 409);
    }
}
