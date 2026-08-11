<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;

final class IndexReportDefinitionRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // ReportDefinitionPolicy::viewAny.
    }

    public function rules(): array
    {
        return array_merge(
            ['report_type' => ['sometimes', 'string', 'max:50']],
            $this->paginationRules(),
        );
    }
}
