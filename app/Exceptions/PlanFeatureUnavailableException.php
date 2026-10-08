<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 403 · La capacidad/módulo solicitado no está incluido en el plan vigente del negocio. No se concede
 * por acceder directamente a su API. No desactiva controles internos de integridad del sistema.
 */
final class PlanFeatureUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Esta función no está incluida en su plan actual.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'PLAN_FEATURE_UNAVAILABLE'], 403);
    }
}
