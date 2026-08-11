<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class GoalConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya existe una meta para ese indicador, sucursal y período.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'GOAL_CONFLICT',
            'message' => $this->getMessage(),
        ], 422);
    }
}
