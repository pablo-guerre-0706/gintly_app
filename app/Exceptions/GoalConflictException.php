<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * ERR-12 · HTTP 422. Ya existe una meta para la misma combinación (negocio, sucursal/global,
 * KPI, tipo de período, inicio). Respaldada por el índice UNIQUE uniq_business_goal: se lanza
 * tanto por la verificación previa como por la colisión concurrente (1062) traducida.
 */
final class GoalConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya existe una meta para ese indicador, ámbito y período.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'GOAL_CONFLICT',
            'message' => $this->getMessage(),
        ], 422);
    }
}
