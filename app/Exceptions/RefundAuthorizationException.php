<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class RefundAuthorizationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El reembolso en efectivo requiere autorización de ROL-01 (Propietario).');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'REFUND_AUTHORIZATION_REQUIRED',
            'message' => $this->getMessage(),
        ], 403);
    }
}
