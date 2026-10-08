<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * HTTP 409 · Misma Idempotency-Key con un payload distinto (fingerprint no coincide). El alta original se
 * conserva intacta; esta solicitud divergente se rechaza sin crear nada.
 */
final class RegistrationIdempotencyConflictException extends Exception
{
    public function __construct(string $message = 'La clave de idempotencia ya se usó con datos distintos.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'REGISTRATION_IDEMPOTENCY_CONFLICT',
        ], 409);
    }
}
