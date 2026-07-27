<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * IMMUTABLE_INVOICE · HTTP 403. Se intentó modificar un campo del núcleo fiscal
 * de una factura emitida. Lanzada por la guarda de modelo de Invoice.
 */
final class ImmutableInvoiceException extends RuntimeException
{
    public static function field(string $field): self
    {
        return new self("El campo \"{$field}\" es parte del núcleo fiscal inmutable de la factura y no puede modificarse.");
    }
}
