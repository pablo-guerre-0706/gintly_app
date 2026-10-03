<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class DispatchExceedsBalanceException extends RuntimeException
{
    public function __construct(
        public readonly int $saleItemId,
        public readonly string $pending,
        public readonly string $requested,
    ) {
        parent::__construct('La cantidad a retirar excede el saldo pendiente de la línea.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'         => 'DISPATCH_EXCEEDS_BALANCE',
            'message'      => $this->getMessage(),
            'sale_item_id' => $this->saleItemId,
            'pending'      => $this->pending,   // Saldo real (ERR-09).
            'requested'    => $this->requested,
        ], 422);
    }
}
