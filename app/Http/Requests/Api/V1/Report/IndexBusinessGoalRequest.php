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
}
