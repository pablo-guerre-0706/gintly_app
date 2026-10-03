<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Anomaly;

use App\Http\Requests\BaseTenantRequest;

final class JustifyAnomalyRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // AnomalyPolicy::justify.
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'], // Justificación requerida
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'justificación'];
    }
}
