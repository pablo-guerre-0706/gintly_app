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
        return [
            'revert_reason' => ['required', 'string', 'max:255'], // Motivo obligatorio (RF-09-04).
        ];
    }

    public function attributes(): array
    {
        return ['revert_reason' => 'motivo de reversión'];
    }
}
