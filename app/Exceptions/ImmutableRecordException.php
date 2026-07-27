<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ImmutableRecordException extends Exception
{
    public function __construct(
        string $message = 'Registro de solo escritura (append-only): no admite modificación ni borrado.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'IMMUTABLE_RECORD'], 403);
    }
}
