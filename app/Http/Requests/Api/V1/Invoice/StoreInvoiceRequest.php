<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Invoice;

use App\Enums\InvoicePaymentType;
use App\Enums\PaymentMethod;
use App\Http\Requests\BaseTenantRequest;
use App\Models\Invoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Emisión de factura. folio, IVA, reserva y user_id son del service.
 * 
 * Validaciones locales:
 *   - sale_ids del tenant y confirmadas.
 *   - contado exige al menos un pago.
 *   - efectivo exige cash_session_id.
 *   - crédito exige cliente NO genérico (exists con is_generic=false).
 * La suma de pagos = total y la suficiencia de stock son del service (necesitan
 * el total calculado y el lock, respectivamente).
 */
final class StoreInvoiceRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sale_ids'   => ['required', 'array', 'min:1'],
            'sale_ids.*' => [
                'required',
                'integer',
                'distinct',
                // Ventas confirmadas del tenant.
                Rule::exists('sales', 'id')
                    ->where('business_id', $this->businessId())
                    ->where('status', \App\Enums\SaleStatus::Confirmada->value),
            ],

            'payment_type' => ['required', Rule::enum(InvoicePaymentType::class)],

            // Obligatorio si hay pago en efectivo (validado en after()).
            'cash_session_id' => [
                'nullable',
                'integer',
                $this->tenantExists('cash_sessions', 'id', excludeTrashed: false),
            ],

            'discount_amount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],

            // payments obligatorio en contado; opcional (típicamente ausente) en crédito.
            'payments'   => ['sometimes', 'array'],
            'payments.*.method' => ['required_with:payments', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'decimal:0,2', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Validaciones cruzadas de forma (sin estado de stock).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $paymentType = InvoicePaymentType::tryFrom((string) $this->input('payment_type'));
                $payments = $this->input('payments', []);

                // Contado exige al menos un pago.
                if ($paymentType === InvoicePaymentType::Contado
                    && (! is_array($payments) || $payments === [])
                ) {
                    $validator->errors()->add(
                        'payments',
                        'Una factura de contado exige al menos un pago.'
                    );
                }

                // Si algún pago es efectivo, cash_session_id es obligatorio.
                if ($this->hasCashPayment() && ! $this->filled('cash_session_id')) {
                    $validator->errors()->add(
                        'cash_session_id',
                        'Un pago en efectivo exige indicar la sesión de caja activa.'
                    );
                }

                // Crédito prohíbe cliente genérico. Se resuelve el cliente desde
                // las ventas: todas comparten cliente (lo revalida el service).
                if ($paymentType === InvoicePaymentType::Credito && $this->creditToGenericCustomer()) {
                    $validator->errors()->add(
                        'payment_type',
                        'No se puede emitir una factura a crédito al cliente genérico "Consumidor Final".'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sale_ids.required'   => 'Debe indicar al menos una venta a facturar.',
            'sale_ids.min'        => 'Debe indicar al menos una venta a facturar.',
            'sale_ids.*.distinct' => 'No repita la misma venta en la factura.',
            'sale_ids.*.exists'   => 'Una venta indicada no existe, no está confirmada o no pertenece a su negocio.',
            'payment_type.required' => 'El tipo de pago es obligatorio.',
            'payment_type.enum'     => 'El tipo de pago debe ser contado o crédito.',
            'cash_session_id.exists' => 'La sesión de caja indicada no existe o no pertenece a su negocio.',
            'discount_amount.decimal' => 'El descuento admite un máximo de dos decimales.',
            'discount_amount.min'     => 'El descuento no puede ser negativo.',
            'payments.*.method.required_with' => 'Cada pago debe indicar su método.',
            'payments.*.amount.required_with' => 'Cada pago debe indicar su monto.',
            'payments.*.amount.gt'            => 'El monto de cada pago debe ser mayor que cero.',
            'payments.*.reference.max'        => 'La referencia no puede exceder los 100 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sale_ids'          => 'ventas',
            'payment_type'      => 'tipo de pago',
            'cash_session_id'   => 'sesión de caja',
            'discount_amount'   => 'descuento',
            'payments'          => 'pagos',
            'payments.*.method' => 'método de pago',
            'payments.*.amount' => 'monto',
        ];
    }

    // True si algún pago declarado es en efectivo.
    private function hasCashPayment(): bool
    {
        foreach ((array) $this->input('payments', []) as $payment) {
            if (($payment['method'] ?? null) === PaymentMethod::Efectivo->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * True si es crédito hacia el cliente genérico. Resuelve el cliente de la
     * primera venta (todas comparten cliente; el service lo revalida).
     */
    private function creditToGenericCustomer(): bool
    {
        $firstSaleId = ((array) $this->input('sale_ids'))[0] ?? null;

        if ($firstSaleId === null) {
            return false;
        }

        return \App\Models\Sale::query()
            ->where('business_id', $this->businessId())
            ->whereKey($firstSaleId)
            ->whereHas('customer', fn ($q) => $q->where('is_generic', true))
            ->exists();
    }
}
