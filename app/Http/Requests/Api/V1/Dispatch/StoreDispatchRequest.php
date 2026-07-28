<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Dispatch;

use App\Http\Requests\BaseTenantRequest;

final class StoreDispatchRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // DispatchPolicy::create en el controlador.
    }

    protected function prepareForValidation(): void
    {
        foreach (['received_by', 'notes'] as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'invoice_id'           => ['required', 'integer', $this->tenantExists('invoices')],
            'received_by'          => ['nullable', 'string', 'max:160'],
            'notes'                => ['nullable', 'string', 'max:500'],

            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.sale_item_id' => ['required', 'integer', 'distinct', $this->tenantExists('sale_items')],
            'lines.*.quantity'     => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
        ];
        // AL SERVICE (bajo lock): factura no anulada (ERR-09B/409), línea pertenece a la factura,
        // tipo servicio (422), saldo pendiente por línea (ERR-09/422).
    }

    public function messages(): array
    {
        return [
            'lines.min'                     => 'El retiro debe incluir al menos una línea.',
            'lines.*.sale_item_id.distinct' => 'No repitas la misma línea de venta dentro de un mismo retiro.',
            'lines.*.quantity.gt'           => 'La cantidad a retirar debe ser mayor que cero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_id'           => 'factura',
            'received_by'          => 'receptor',
            'notes'                => 'observación',
            'lines'                => 'líneas',
            'lines.*.sale_item_id' => 'línea de venta',
            'lines.*.quantity'     => 'cantidad',
        ];
    }
}
