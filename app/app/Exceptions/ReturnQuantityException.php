<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ReturnQuantityException extends RuntimeException
{
    public function __construct(
        public readonly int $saleItemId,
        public readonly string $returnable,
        public readonly string $requested,
    ) {
        parent::__construct('La cantidad a devolver excede lo efectivamente entregado de la línea.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'         => 'RETURN_QUANTITY',
            'message'      => $this->getMessage(),
            'sale_item_id' => $this->saleItemId,
            'returnable'   => $this->returnable, // Máximo devolvible real (ERR-10).
            'requested'    => $this->requested,
        ], 422);
    }
}
