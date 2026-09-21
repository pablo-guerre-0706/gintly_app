<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvoiceVoidedException extends RuntimeException
{
    public function __construct(
        string $message = 'No se puede abonar a una cuenta cuya factura ya ha sido anulada.',
    ) {
        parent::__construct($message);
    }

    /**
     * Conflicto de estado: la factura de la CxC está anulada (409),
     * simétrico con DispatchOnVoidedInvoiceException (ERR-09B). Mapeo central:
     * el Handler global invoca este render(); el controlador no captura nada.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'INVOICE_VOIDED',
            'message' => $this->getMessage(),
        ], 409);
    }
}
