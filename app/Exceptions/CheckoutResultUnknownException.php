<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 409 · El resultado de un intento de checkout ANTERIOR con esta misma Idempotency-Key es INCIERTO: la
 * llamada al proveedor falló tras enviarse y no se confirmó la URL. Como la API de checkout de Lemon Squeezy no
 * admite idempotencia de creación, NO se reintenta a ciegas (podría duplicar el checkout). El cliente debe
 * reintentar con una Idempotency-Key NUEVA; un checkout huérfano nunca concede acceso ni crea suscripción por sí
 * solo (solo el webhook de pago lo hace).
 */
final class CheckoutResultUnknownException extends RuntimeException
{
    public function __construct(string $message = 'No se pudo confirmar el intento de contratación anterior. Reintente con una nueva solicitud.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'CHECKOUT_RESULT_UNKNOWN'], 409);
    }
}
