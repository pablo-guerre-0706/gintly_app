<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CyclicReferenceException extends Exception
{
    public function __construct(string $message = 'La operación crearía una referencia cíclica.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'CYCLIC_REFERENCE'], 422);
    }
}
