<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 409 · El negocio YA tiene una suscripción pagada vigente. No se abre un nuevo checkout (evitaría una
 * SEGUNDA suscripción recurrente para el mismo negocio en el proveedor): para cambiar de plan o cancelar se usan
 * los endpoints de gestión de la MISMA suscripción.
 */
final class SubscriptionAlreadyActiveException extends RuntimeException
{
    public function __construct(string $message = 'Su negocio ya tiene una suscripción vigente. Use el cambio de plan o la cancelación.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'SUBSCRIPTION_ALREADY_ACTIVE'], 409);
    }
}
