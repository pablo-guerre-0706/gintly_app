<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\PeriodType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use Illuminate\Validation\Rule;

final class ReportRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;

    public function authorize(): bool
    {
        return true; // ReportDefinitionPolicy::generateReport.
    }

    public function rules(): array
    {
        return array_merge(
            [
                'period_type' => ['sometimes', 'string', Rule::in(PeriodType::values())],
                'branch_id'   => ['sometimes', 'integer', $this->tenantExists('branches')],
            ],
            $this->dateRangeRules(), // from / to
        );
    }
}
