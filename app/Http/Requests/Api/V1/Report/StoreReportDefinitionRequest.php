<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\BaseTenantRequest;

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
            'report_type'   => ['required', 'string', 'max:50'],
            'filters'       => ['nullable', 'array'],
            'is_scheduled'  => ['sometimes', 'boolean'],
            'schedule_cron' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'report_type' => 'tipo de reporte', 'filters' => 'filtros'];
    }
}
