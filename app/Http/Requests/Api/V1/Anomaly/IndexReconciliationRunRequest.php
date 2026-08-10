<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Anomaly;

use App\Enums\ReconciliationRunType;
use App\Enums\ReconciliationScope;
use App\Enums\ReconciliationStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexReconciliationRunRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // ReconciliationRunPolicy::viewAny.
    }

    public function rules(): array
    {
        return array_merge(
            [
                'scope'    => ['sometimes', 'string', Rule::in(ReconciliationScope::values())],
                'run_type' => ['sometimes', 'string', Rule::in(ReconciliationRunType::values())],
                'status'   => ['sometimes', 'string', Rule::in(ReconciliationStatus::values())],
            ],
            $this->dateRangeRules(),
            $this->paginationRules(),
        );
    }

    public function attributes(): array
    {
        return ['scope' => 'alcance', 'run_type' => 'tipo', 'status' => 'estado'];
    }
}
