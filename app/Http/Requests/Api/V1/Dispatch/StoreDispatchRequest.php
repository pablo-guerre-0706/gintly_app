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
        // notes en blanco ⇒ null. received_by es obligatorio (RF-09-01: receptor declarado);
        // no se anula: un valor en blanco debe fallar la validación 'required'.
        if ($this->has('notes') && trim((string) $this->input('notes')) === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            // invoices y sale_items NO son soft-deletable ⇒ excludeTrashed:false evita el
            // whereNull('deleted_at') sobre una columna inexistente (SQLSTATE 42S22 → 500).
            'invoice_id'           => ['required', 'integer', $this->tenantExists('invoices', 'id', excludeTrashed: false)],
            // Receptor declarado obligatorio (RF-09-01): trazabilidad de la entrega física.
            'received_by'          => ['required', 'string', 'min:2', 'max:160'],
            'notes'                => ['nullable', 'string', 'max:500'],

            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.sale_item_id' => ['required', 'integer', 'distinct', $this->tenantExists('sale_items', 'id', excludeTrashed: false)],
            'lines.*.quantity'     => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
        ];
        // AL SERVICE (bajo lock): factura no anulada (ERR-09B/409), línea pertenece a la factura,
        // tipo servicio/no inventariable (422), saldo pendiente por línea (ERR-09/422), sucursal (403).
    }

    public function messages(): array
    {
        return [
            'received_by.required'          => 'Debe declarar quién recibe la mercancía.',
            'received_by.min'               => 'El receptor declarado no es válido.',
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
