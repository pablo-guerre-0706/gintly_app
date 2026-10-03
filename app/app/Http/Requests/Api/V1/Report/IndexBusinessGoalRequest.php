<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\BusinessGoalKpiCode;
use App\Enums\PeriodType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexBusinessGoalRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // BusinessGoalPolicy::viewAny.
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }

    /**
     * Allowlist de ordenamiento (H-04): HasPaginationRules lo declara abstracto (sin él la clase no carga).
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'kpi_code', 'period_type', 'period_start', 'period_end', 'created_at'];
    }

    public function rules(): array
    {
        return array_merge(
            [
                'kpi_code'    => ['sometimes', 'string', Rule::in(BusinessGoalKpiCode::values())],
                'period_type' => ['sometimes', 'string', Rule::in(PeriodType::values())],
                'branch_id'   => ['sometimes', 'integer', $this->tenantExists('branches')],
            ],
            $this->paginationRules(),
        );
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }
}
