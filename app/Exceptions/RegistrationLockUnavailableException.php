<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 500 SANITIZADO · No se pudo adquirir el lock de serialización por Idempotency-Key (timeout o error de
 * infraestructura del motor). NO es un conflicto de slug ni de idempotencia: es indisponibilidad temporal. El
 * cliente debe reintentar con la MISMA Idempotency-Key (operación idempotente). El render no expone SQL, el
 * nombre del lock, fingerprints ni trazas.
 */
final class RegistrationLockUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'No se pudo completar el registro por indisponibilidad temporal. Reintente con la misma Idempotency-Key.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 500);
    }
}
