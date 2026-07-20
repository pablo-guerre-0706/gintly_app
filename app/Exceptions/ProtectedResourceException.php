<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ProtectedResourceException extends Exception
{
    public function __construct(string $message = 'Este recurso está protegido y no puede modificarse ni eliminarse.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'PROTECTED_RESOURCE'], 403);
    }
}
