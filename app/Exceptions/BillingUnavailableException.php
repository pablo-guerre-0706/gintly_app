<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 503 SANITIZADO · El subsistema de cobro no puede operar ahora: configuración ausente/incoherente
 * (p. ej. demo con modo live), proveedor indisponible o variante no verificable. NUNCA fabrica una URL de
 * checkout ni un pago. No expone secretos, SQL ni detalles internos.
 */
final class BillingUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'La contratación no está disponible en este momento. Intente más tarde.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'BILLING_UNAVAILABLE'], 503);
    }
}
