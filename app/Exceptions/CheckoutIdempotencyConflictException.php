<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 409 · La misma Idempotency-Key de checkout se reutilizó con un plan/periodicidad distintos. El intento
 * original se conserva; esta solicitud divergente se rechaza sin crear otra contratación.
 */
final class CheckoutIdempotencyConflictException extends RuntimeException
{
    public function __construct(string $message = 'La clave de idempotencia ya se usó con una contratación distinta.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'CHECKOUT_IDEMPOTENCY_CONFLICT'], 409);
    }
}
