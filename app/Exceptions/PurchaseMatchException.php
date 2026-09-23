<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /**
     * la recepción YA quedó persistida antes del throw. Se emite 409 transportando
     * el receipt guardado para que ROL-01 lo resuelva, sin try/catch ni transacción en el controlador.
     */
    public function render(Request $request): JsonResponse
    {
        // Cuerpo canónico: { message, code:'PURCHASE_MATCH', data: GoodsReceiptResource }.
        // `code` es un código semántico ESTABLE para el frontend; `data` transporta el
        // recibo persistido (con items[].matched y account_payable congelada). No se
        // usa la clave duplicada `goods_receipt`.
        return (new GoodsReceiptResource(
            $this->receipt->load(['items.product', 'accountPayable'])
        ))
            ->additional([
                'message' => $this->getMessage(),
                'code'    => 'PURCHASE_MATCH',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CONFLICT);
    }
}
