<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * INCOMPLETE_PAYMENT · HTTP 422. Una factura de contado cuyo pago no cubre el
 * 100% del total (ERR-07). NADA se persiste (rollback estricto).
 */
final class IncompletePaymentException extends RuntimeException
{
    public static function make(string $paid, string $total): self
    {
        return new self("El pago de contado ({$paid}) no cubre el total de la factura ({$total}).");
    }
}
