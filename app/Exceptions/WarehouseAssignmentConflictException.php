<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * HTTP 409. El par (bodega, bodeguero) ya tiene una asignación ACTIVA (no se duplica la vigencia).
 * Self-render con clave `error` (simétrico con las demás excepciones de asignación de caja/bodega).
 */
final class WarehouseAssignmentConflictException extends RuntimeException
{
    public static function alreadyAssigned(): self
    {
        return new self('WAREHOUSE_ALREADY_ASSIGNED');
    }

    private function __construct(public readonly string $errorCode)
    {
        parent::__construct('El bodeguero ya tiene una asignación activa sobre esta bodega.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => $this->errorCode,
            'message' => $this->getMessage(),
        ], 409);
    }
}
