<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class CashSessionConflictException extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function registerBusy(): self
    {
        return new self(
            'CASH_REGISTER_BUSY',
            'La caja ya tiene una sesión abierta. Debe cerrarse antes de abrir otra.',
        );
    }

    public static function userBusy(): self
    {
        return new self(
            'CASH_USER_BUSY',
            'El usuario ya tiene una sesión de caja abierta. Debe cerrarla antes de abrir otra.',
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => $this->errorCode,
            'message' => $this->getMessage(),
        ], 409); // Conflict — simétrico para ambos candados de motor
    }
}
