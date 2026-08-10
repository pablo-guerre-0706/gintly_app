<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class SelfResolutionNotAllowedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El causante de una anomalía no puede justificarla (BR-01).');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'SELF_RESOLUTION_NOT_ALLOWED',
            'message' => $this->getMessage(),
        ], 403);
    }
}
