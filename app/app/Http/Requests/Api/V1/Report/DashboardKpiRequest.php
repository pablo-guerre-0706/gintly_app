<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\PeriodType;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class DashboardKpiRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // KpiSnapshotPolicy::viewDashboard.
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('period_type')) {
            $this->merge(['period_type' => PeriodType::Mensual->value]); // Default mensual.
        }
    }

    public function rules(): array
    {
        return [
            'period_type'  => ['required', 'string', Rule::in(PeriodType::values())],
            'period_start' => ['nullable', 'date'],
        ];
    }
}
