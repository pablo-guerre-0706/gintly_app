<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\PeriodType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexKpiSnapshotRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // KpiSnapshotPolicy::viewAny.
    }

    public function rules(): array
    {
        return array_merge(
            [
                'kpi_code'     => ['sometimes', 'string', 'max:50'],
                'period_type'  => ['sometimes', 'string', Rule::in(PeriodType::values())],
                'period_start' => ['sometimes', 'date'],
                'branch_id'    => ['sometimes', 'integer', $this->tenantExists('branches')],
            ],
            $this->paginationRules(),
        );
    }
}
