<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SalesReturn;

use App\Enums\ReturnDestination;
use App\Enums\ReturnReasonCode;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreSalesReturnRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // SalesReturnPolicy::create en el controlador.
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && trim((string) $this->input('notes')) === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'invoice_id'      => ['required', 'integer', $this->tenantExists('invoices')],
            'cash_session_id' => ['nullable', 'integer', $this->tenantExists('cash_sessions')],
            'notes'           => ['nullable', 'string', 'max:500'],

            'lines'                 => ['required', 'array', 'min:1'],
            'lines.*.sale_item_id'  => ['required', 'integer', $this->tenantExists('sale_items')],
            'lines.*.quantity'      => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'lines.*.reason_code'   => ['required', 'string', Rule::in(ReturnReasonCode::values())],
            'lines.*.destination'   => ['nullable', 'string', Rule::in(ReturnDestination::values())],
            'lines.*.warehouse_id'  => ['nullable', 'integer', $this->tenantExists('warehouses')],
        ];
        // AL SERVICE (bajo lock): devolvible por línea (ERR-10/422), vía de resarcimiento (ERR-10B/422),
        // autoridad ROL-01 del reembolso (403), pertenencia de la línea a la factura.
    }

    public function withValidator(Validator $validator): void
    {
        // Coherencia motivo⇄destino: 'vencido'/'defecto_fabrica' NUNCA reingresan (RF-10-01).
        $validator->after(function (Validator $v): void {
            foreach ((array) $this->input('lines', []) as $i => $line) {
                $destination = $line['destination'] ?? null;
                $reason      = $line['reason_code'] ?? null;

                if ($destination === ReturnDestination::Reingreso->value
                    && is_string($reason)
                    && ! ReturnReasonCode::from($reason)->allowsReentry()
                ) {
                    $v->errors()->add(
                        "lines.$i.destination",
                        'Un producto vencido o con defecto de fábrica no puede reingresar al stock; corresponde merma.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'lines.min'              => 'La devolución debe incluir al menos una línea.',
            'lines.*.quantity.gt'    => 'La cantidad a devolver debe ser mayor que cero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_id'           => 'factura',
            'cash_session_id'      => 'sesión de caja',
            'lines.*.sale_item_id' => 'línea de venta',
            'lines.*.quantity'     => 'cantidad',
            'lines.*.reason_code'  => 'motivo',
            'lines.*.destination'  => 'destino',
            'lines.*.warehouse_id' => 'bodega',
        ];
    }
}
