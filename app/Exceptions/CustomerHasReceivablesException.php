<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// CUSTOMER_HAS_RECEIVABLES · HTTP 422. Intentó dar de baja un cliente con cuentas por cobrar pendientes.
final class CustomerHasReceivablesException extends RuntimeException
{
    public static function make(int $customerId): self
    {
        return new self("El cliente {$customerId} tiene cuentas por cobrar pendientes y no puede darse de baja.");
    }
}

