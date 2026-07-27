<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\CashSession;
use RuntimeException;

/**
 * UNRECONCILED_CASH_CLOSING · HTTP 422. El arqueo arrojó diferencia ≠ 0. 
 */
final class UnreconciledCashClosingException extends RuntimeException
{
    public function __construct(
        public readonly CashSession $session,
        string $message = 'El cierre de caja presenta una diferencia respecto al efectivo esperado.'
    ) {
        parent::__construct($message);
    }
}
