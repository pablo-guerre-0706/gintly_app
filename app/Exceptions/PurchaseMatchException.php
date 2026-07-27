<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\GoodsReceipt;
use RuntimeException;


// HTTP 409. El 3-Way Match halló discrepancia.
final class PurchaseMatchException extends RuntimeException
{
    public function __construct(
        public readonly GoodsReceipt $receipt,
        string $message = 'La recepción presenta discrepancias en el cruce de tres vías.'
    ) {
        parent::__construct($message);
    }
}
