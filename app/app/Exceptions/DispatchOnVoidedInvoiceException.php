<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class DispatchOnVoidedInvoiceException extends RuntimeException
{
    public function __construct(public readonly int $invoiceId)
    {
        parent::__construct('No se puede retirar mercancía de una factura anulada.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'       => 'DISPATCH_ON_VOIDED_INVOICE',
            'message'    => $this->getMessage(),
            'invoice_id' => $this->invoiceId,
        ], 409);
    }
}
