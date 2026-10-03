<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Receivables;

use App\Http\Requests\BaseTenantRequest;

final class CreditCheckRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // CustomerPolicy::checkCredit en el controlador.
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'monto'];
    }
}
