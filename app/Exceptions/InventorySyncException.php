<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InventorySyncException extends Exception
{
    public function __construct(
        string $message = 'Fallo de atomicidad: movimiento sin actualización de saldo. Transacción revertida.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'INVENTORY_SYNC'], 500);
    }
}
