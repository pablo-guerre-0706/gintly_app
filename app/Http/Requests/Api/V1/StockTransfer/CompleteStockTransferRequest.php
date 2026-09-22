<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\StockTransfer;

use App\Http\Requests\BaseTenantRequest;

/**
 * Opción A: la confirmación consume las líneas persistidas al crear el traspaso.
 * El endpoint NO acepta ítems nuevos; cualquier `items` del cuerpo se ignora.
 */
final class CompleteStockTransferRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // Autorización 'complete' centralizada en el controlador (StockTransferPolicy).
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
