<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 403 · El negocio no tiene una suscripción pagada vigente; sus usuarios (incluido ROL-01) no pueden
 * operar los módulos del ERP. Distinto de 401 (sin sesión) y de una denegación por permisos de rol/perfil.
 */
final class SubscriptionRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Su negocio no tiene una suscripción activa. Contrate un plan para operar.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'SUBSCRIPTION_REQUIRED'], 403);
    }
}
