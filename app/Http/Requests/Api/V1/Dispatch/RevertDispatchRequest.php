<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Dispatch;

use App\Http\Requests\BaseTenantRequest;

final class RevertDispatchRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // DispatchPolicy::revert en el controlador.
    }

    public function rules(): array
    {
        // Motivo obligatorio y SIGNIFICATIVO (RF-09-04): min 3 caracteres, como la anulación de factura.
        return [
            'revert_reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'revert_reason.required' => 'El motivo de la reversión es obligatorio.',
            'revert_reason.min'      => 'El motivo de la reversión debe tener al menos 3 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return ['revert_reason' => 'motivo de reversión'];
    }
}
