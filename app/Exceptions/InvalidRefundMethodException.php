<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidRefundMethodException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /** Reembolso en efectivo sobre venta a crédito con saldo pendiente. */
    public static function cashOnUnpaidCredit(): self
    {
        return new self('No se reembolsa en efectivo un monto que el cliente aún no ha pagado; el resarcimiento reduce primero la cuenta por cobrar.');
    }

    /** Reembolso en efectivo sin sesión de caja activa. */
    public static function noActiveCashSession(): self
    {
        return new self('El reembolso en efectivo exige una sesión de caja activa.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'INVALID_REFUND_METHOD',
            'message' => $this->getMessage(),
        ], 422);
    }
}
