<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class CreditLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $exposure,
        public readonly string $limit,
        string $message = 'La venta a crédito excede el cupo disponible del cliente.'
    ) {
        parent::__construct($message);
    }

    /** Cliente sin línea de crédito (límite = 0). No puede operar a crédito. */
    public static function noCreditLine(string $limit): self
    {
        return new self(
            exposure: '0.00',
            limit: $limit,
            message: 'El cliente no tiene línea de crédito habilitada.'
        );
    }

    /** Exposición + monto supera el límite y no hay autorización ROL-01. */
    public static function overLimit(string $exposure, string $limit): self
    {
        return new self($exposure, $limit);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'     => 'CREDIT_LIMIT_EXCEEDED',
            'message'  => $this->getMessage(),
            'exposure' => $this->exposure, // Respuesta auditable (RF-08-02).
            'limit'    => $this->limit,
        ], 422);
    }
}
