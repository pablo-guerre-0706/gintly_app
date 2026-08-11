<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\BaseTenantRequest;

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
            'report_type'   => ['sometimes', 'string', 'max:50'],
            'filters'       => ['sometimes', 'nullable', 'array'],
            'is_scheduled'  => ['sometimes', 'boolean'],
            'schedule_cron' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
