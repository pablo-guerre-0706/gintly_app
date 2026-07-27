<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// ERR-04B · HTTP 422. Se intentó crear o emitir una orden hacia un proveedor no aprobado.
final class SupplierNotApprovedException extends RuntimeException
{
    public static function make(int $supplierId): self
    {
        return new self("El proveedor {$supplierId} no está aprobado y no puede recibir órdenes de compra.");
    }
}
