<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\PeriodType;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class RecalculateKpiRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // KpiSnapshotPolicy::recalculate.
    }

    public function rules(): array
    {
        return [
            'period_type'    => ['required', 'string', Rule::in(PeriodType::values())],
            'reference_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return ['period_type' => 'tipo de período', 'reference_date' => 'fecha de referencia'];
    }
}
