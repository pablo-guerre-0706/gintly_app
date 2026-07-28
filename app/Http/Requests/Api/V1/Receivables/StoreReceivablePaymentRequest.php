<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Receivables;

use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class StoreReceivablePaymentRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // AccountReceivablePolicy::pay en el controlador.
    }

    protected function prepareForValidation(): void
    {
        // Referencia en blanco ⇒ null (coherencia con la columna nullable).
        if ($this->has('reference') && trim((string) $this->input('reference')) === '') {
            $this->merge(['reference' => null]);
        }
    }

    public function rules(): array
    {
        return [
            // '>0' estricto (ERR-08 rechaza amount <= 0). decimal:0,2 acota la escala monetaria.
            'amount'          => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'payment_method'  => ['required', 'string', Rule::in(PaymentMethod::values())],
            'cash_session_id' => [
                'nullable',
                'required_if:payment_method,efectivo', // Efectivo exige caja (existencia).
                'integer',
                $this->tenantExists('cash_sessions'),  // Que la sesión sea del mismo negocio.
            ],
            'reference'       => ['nullable', 'string', 'max:100'],
        ];
        // NO validado aquí (va al Service bajo lock):
        //  - amount <= balance  ⇒ OverpaymentException (422)
        //  - la sesión esté ABIERTA ⇒ NoActiveCashSessionException (409, ERR-08B)
    }

    public function messages(): array
    {
        return [
            'amount.gt'                   => 'El monto del abono debe ser mayor que cero.',
            'cash_session_id.required_if' => 'Un abono en efectivo exige una sesión de caja activa.',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount'          => 'monto',
            'payment_method'  => 'medio de pago',
            'cash_session_id' => 'sesión de caja',
            'reference'       => 'referencia',
        ];
    }
}
