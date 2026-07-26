<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// ERR-03 · HTTP 409. Una operación dejaría la existencia física por debajo de cero.
// Materialización en dominio de chk_stock_qty_non_negative.
final class InsufficientStockException extends RuntimeException
{
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
