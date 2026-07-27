<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * HTTP 422. El autorizante de un egreso autorizado no tiene rango de ROL-02.
 * El CHECK de motor solo garantiza que authorized_by no sea nulo; la
 * autoridad del rol se verifica aquí.
 */
final class CashAuthorizationException extends RuntimeException
{
    public static function notAdmin(int $userId): self
    {
        return new self("El usuario {$userId} no tiene autoridad para autorizar egresos de caja.");
    }
}

