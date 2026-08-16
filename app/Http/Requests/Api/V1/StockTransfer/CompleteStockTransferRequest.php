<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\StockTransfer;

use App\Http\Requests\BaseTenantRequest;

final class CompleteStockTransferRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // Autorización 'complete' centralizada en el controlador (StockTransferPolicy).
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1'],
            // DM3-01: multi-línea. product_id acotado al tenant (D5); distinct evita procesar dos veces el mismo producto.
            'items.*.product_id' => ['required', 'integer', 'distinct', $this->tenantExists('products')],
            // decimal(14,3) bcmath: string con hasta 3 decimales y estrictamente > 0.
            'items.*.quantity'   => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.*.quantity.regex' => 'La cantidad debe ser un decimal con hasta 3 posiciones.',
            'items.*.quantity.gt'    => 'La cantidad debe ser mayor que cero.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'items.*.product_id' => 'producto',
            'items.*.quantity'   => 'cantidad',
        ];
    }
}
