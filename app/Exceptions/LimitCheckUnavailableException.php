<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 503 SANITIZADO · No se pudo COMPROBAR el límite del plan porque la serialización (lock con nombre) no
 * se adquirió a tiempo. Es un problema de INFRAESTRUCTURA, no un cupo superado: NO se afirma que el negocio
 * excedió su plan y NO se confunde con PLAN_LIMIT_EXCEEDED. No expone SQL, nombres de lock ni detalles internos;
 * la causa concreta queda en el log del servidor (report()), no en la respuesta.
 */
final class LimitCheckUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'No se pudo verificar el límite de su plan en este momento. Intente nuevamente.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'LIMIT_CHECK_UNAVAILABLE'], 503);
    }
}
