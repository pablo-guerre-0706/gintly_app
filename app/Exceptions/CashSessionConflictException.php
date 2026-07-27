<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * HTTP 409. La caja ya tiene una sesión abierta (open_register_lock) o el
 * usuario ya tiene una sesión abierta en otra caja (open_user_lock). Traduce la
 * violación de unicidad de motor (1062) a un mensaje de negocio legible.
 */
final class CashSessionConflictException extends RuntimeException
{
    public static function registerBusy(): self
    {
        return new self('La caja ya tiene una sesión abierta. Ciérrela antes de abrir otra.');
    }

    public static function userBusy(): self
    {
        return new self('Usted ya tiene una sesión de caja abierta. Ciérrela antes de abrir otra.');
    }
}
