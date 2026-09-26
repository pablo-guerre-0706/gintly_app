<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\ReportType;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class StoreReportDefinitionRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // ReportDefinitionPolicy::create.
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:120'],
            'report_type'   => ['required', 'string', Rule::in(ReportType::values())], // allowlist
            // filters: estructura JSON validada (nunca SQL libre). Claves acotadas al reporte.
            'filters'         => ['nullable', 'array'],
            'filters.branch_id'   => ['sometimes', 'nullable', 'integer', $this->tenantExists('branches')],
            'filters.period_type' => ['sometimes', 'string', Rule::in(\App\Enums\PeriodType::values())],
            'filters.from'        => ['sometimes', 'date_format:Y-m-d'],
            'filters.to'          => ['sometimes', 'date_format:Y-m-d'],
            'is_scheduled'  => ['sometimes', 'boolean'],
            'schedule_cron' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'report_type' => 'tipo de reporte', 'filters' => 'filtros'];
    }
}
