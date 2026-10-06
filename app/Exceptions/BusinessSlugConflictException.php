<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * HTTP 409 · Se agotaron los reintentos de slug por colisiones REALES del índice `businesses_slug_unique`.
 * NO se emite por deadlocks ni por otros errores transaccionales (esos tienen su propio tratamiento).
 */
final class BusinessSlugConflictException extends Exception
{
    public function __construct(string $message = 'No se pudo generar un identificador único para el negocio. Reintente.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'BUSINESS_SLUG_CONFLICT',
        ], 409);
    }
}
