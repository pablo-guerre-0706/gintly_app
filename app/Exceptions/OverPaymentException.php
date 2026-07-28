<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class OverpaymentException extends RuntimeException
{
    public function __construct(
        public readonly string $balance,
        public readonly string $amount,
        string $message = 'El abono no puede exceder el saldo de la cuenta.'
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'OVERPAYMENT',
            'message' => $this->getMessage(),
            'balance' => $this->balance,
            'amount'  => $this->amount,
        ], 422);
    }
}
