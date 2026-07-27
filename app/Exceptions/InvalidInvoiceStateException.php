<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * HTTP 422/409. Estado inválido en el flujo de venta o factura: venta vacía o
 * no abierta al confirmar, ventas de distinto cliente/sucursal al facturar,
 * factura no emitida al anular, folio en conflicto.
 */
final class InvalidInvoiceStateException extends RuntimeException
{
    public static function saleNotEditable(int $saleId): self
    {
        return new self("La venta {$saleId} no está abierta y no admite cambios en sus ítems.");
    }

    public static function saleEmpty(int $saleId): self
    {
        return new self("La venta {$saleId} no tiene ítems y no puede confirmarse.");
    }

    public static function saleNotConfirmable(int $saleId): self
    {
        return new self("La venta {$saleId} no está abierta y no puede confirmarse.");
    }

    public static function salesNotHomogeneous(): self
    {
        return new self('Todas las ventas de una factura deben ser del mismo cliente y la misma sucursal.');
    }

    public static function saleNotConfirmed(int $saleId): self
    {
        return new self("La venta {$saleId} no está confirmada y no puede facturarse.");
    }

    public static function creditToGeneric(): self
    {
        return new self('No se puede emitir una factura a crédito al cliente genérico.');
    }

    public static function invoiceNotVoidable(int $invoiceId): self
    {
        return new self("La factura {$invoiceId} no está emitida y no puede anularse.");
    }

    public static function folioConflict(): self
    {
        return new self('Colisión de folio al emitir la factura. Reintente la operación.');
    }
}
