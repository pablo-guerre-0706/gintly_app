<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\PeriodType;
use App\Enums\ReportType;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class UpdateReportDefinitionRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // ReportDefinitionPolicy::update.
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:120'],
            'report_type'   => ['sometimes', 'string', Rule::in(ReportType::values())], // allowlist
            'filters'       => ['sometimes', 'nullable', 'array'],
            'filters.branch_id'   => ['sometimes', 'nullable', 'integer', $this->tenantExists('branches')],
            'filters.period_type' => ['sometimes', 'string', Rule::in(PeriodType::values())],
            'filters.from'        => ['sometimes', 'date_format:Y-m-d'],
            'filters.to'          => ['sometimes', 'date_format:Y-m-d'],
            'is_scheduled'  => ['sometimes', 'boolean'],
            'schedule_cron' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
