<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Anomaly;

use App\Enums\AnomalySeverity;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class UpdateAnomalyRuleRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // AnomalyRulePolicy::update.
    }

    public function rules(): array
    {
        return [
            'threshold_value'  => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'default_severity' => ['sometimes', 'string', Rule::in(AnomalySeverity::values())],
            'is_active'        => ['sometimes', 'boolean'],
        ];
        // code y name NO son editables (catálogo cerrado).
    }

    public function attributes(): array
    {
        return [
            'threshold_value'  => 'umbral',
            'default_severity' => 'severidad por defecto',
            'is_active'        => 'activa',
        ];
    }
}
