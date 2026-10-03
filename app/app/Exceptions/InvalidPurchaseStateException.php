<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// HTTP 409 (por defecto) para transiciones inválidas del flujo de compras.
// Excepción: sobrepago de CxP, que el contrato define como 422 (validación).
final class InvalidPurchaseStateException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }

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
        // 422 (validación de monto), no 409: el contrato distingue "excede saldo" de "estado inválido".
        return new self("El monto del pago excede el saldo pendiente de la cuenta {$payableId}.", 422);
    }

    public static function orderNotReceivable(int $orderId): self
    {
        return new self("La orden {$orderId} no está en estado receptible (emitida o parcial).");
    }
}
