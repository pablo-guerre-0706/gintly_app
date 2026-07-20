<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SupplierNotApprovedException extends Exception
{
    public function __construct(
        string $message = 'El proveedor no está aprobado: no admite órdenes de compra.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'SUPPLIER_NOT_APPROVED'], 422);
    }
}
