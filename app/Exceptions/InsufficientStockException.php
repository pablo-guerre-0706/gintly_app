<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


// ERR-03 · HTTP 409. Una operación dejaría la existencia física por debajo de cero.
// Materialización en dominio de chk_stock_qty_non_negative.
final class InsufficientStockException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'INSUFFICIENT_STOCK',
            'message' => $this->getMessage(),
        ], 409);
    }
    public static function make(int $productId, int $warehouseId, string $available, string $requested): self
    {
        return new self(sprintf(
            'Stock insuficiente para el producto %d en la bodega %d: disponible %s, solicitado %s.',
            $productId,
            $warehouseId,
            $available,
            $requested
        ));
    }
}
