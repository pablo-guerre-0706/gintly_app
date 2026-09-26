<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\ReportType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexReportDefinitionRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // ReportDefinitionPolicy::viewAny.
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }

    /**
     * Allowlist de ordenamiento (H-04): HasPaginationRules lo declara abstracto.
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'name', 'report_type', 'created_at', 'updated_at'];
    }

    public function rules(): array
    {
        return array_merge(
            ['report_type' => ['sometimes', 'string', Rule::in(ReportType::values())]],
            $this->paginationRules(),
        );
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }
}
