<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\BaseTenantRequest;

final class UpdateBusinessGoalRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // BusinessGoalPolicy::update.
    }

    public function rules(): array
    {
        return [
            'target_value' => ['sometimes', 'numeric', 'decimal:0,2', 'gt:0'],
            'period_end'   => ['sometimes', 'date', 'after_or_equal:period_start'],
            'period_start' => ['sometimes', 'date'],
        ];
        // kpi_code / period_type NO se editan (romperían la unicidad histórica).
    }

    public function attributes(): array
    {
        return ['target_value' => 'meta', 'period_end' => 'fin del período', 'period_start' => 'inicio del período'];
    }
}
