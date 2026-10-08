<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 409 · Se superaría un límite del plan vigente (sucursales activas o cajas operando simultáneamente).
 * El límite es del negocio completo (ROL-01 incluido). No borra ni desactiva recursos para hacer encajar.
 */
final class PlanLimitExceededException extends RuntimeException
{
    public function __construct(string $message = 'Se alcanzó el límite de su plan.')
    {
        parent::__construct($message);
    }

    public static function branches(int $limit): self
    {
        return new self("Su plan permite un máximo de {$limit} sucursal(es) activa(s).");
    }

    public static function cashSessions(int $limit): self
    {
        return new self("Su plan permite un máximo de {$limit} caja(s) operando simultáneamente.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'PLAN_LIMIT_EXCEEDED'], 409);
    }
}
