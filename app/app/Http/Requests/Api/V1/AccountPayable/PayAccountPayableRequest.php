<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\AccountPayable;

use App\Http\Requests\BaseTenantRequest;
use App\Models\AccountPayable;


final class PayAccountPayableRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('account_payable');

        return $target instanceof AccountPayable
            && ($this->user()?->can('pay', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount'   => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required'     => 'El monto del pago es obligatorio.',
            'amount.decimal'      => 'El monto admite un máximo de dos decimales.',
            'amount.gt'           => 'El monto del pago debe ser mayor que cero.',
            'due_date.date_format' => 'La fecha de vencimiento debe expresarse en formato AAAA-MM-DD.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount'   => 'monto',
            'due_date' => 'fecha de vencimiento',
        ];
    }
}
