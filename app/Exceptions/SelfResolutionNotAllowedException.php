<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


class SelfResolutionNotAllowedException extends Exception
{
    public function __construct(string $message = 'No puede justificar una anomalía en la que está involucrado (BR-01).')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'SELF_RESOLUTION_NOT_ALLOWED'], 403);
    }
}
