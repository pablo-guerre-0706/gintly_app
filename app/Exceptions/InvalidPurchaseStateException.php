<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// HTTP 409. Transición inválida en el flujo de compras.
final class InvalidPurchaseStateException extends RuntimeException
{
    public static function orderNotEditable(int $orderId): self
    {
        return new self("La orden {$orderId} no está en borrador y no admite edición.");
    }

    public static function orderNotIssuable(int $orderId): self
    {
        return new self("La orden {$orderId} no puede emitirse desde su estado actual.");
    }

    public static function orderNotCancellable(int $orderId): self
    {
        return new self("La orden {$orderId} no puede cancelarse desde su estado actual.");
    }

    public static function receiptNotResolvable(int $receiptId): self
    {
        return new self("La recepción {$receiptId} no está en discrepancia y no admite resolución.");
    }

    public static function payableBlocked(int $payableId): self
    {
        return new self("La cuenta por pagar {$payableId} está congelada y no admite pagos.");
    }

    public static function payableNotBlocked(int $payableId): self
    {
        return new self("La cuenta por pagar {$payableId} no está congelada.");
    }

    public static function paymentExceedsBalance(int $payableId): self
    {
        return new self("El monto del pago excede el saldo pendiente de la cuenta {$payableId}.");
    }

    public static function orderNotReceivable(int $orderId): self
    {
        return new self("La orden {$orderId} no está en estado receptible (emitida o parcial).");
    }
}
