<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DispatchOnVoidedInvoiceException extends Exception
{
    public function __construct(string $message = 'No se puede retirar mercancía contra una factura anulada.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'DISPATCH_ON_VOIDED_INVOICE'], 409);
    }
}
