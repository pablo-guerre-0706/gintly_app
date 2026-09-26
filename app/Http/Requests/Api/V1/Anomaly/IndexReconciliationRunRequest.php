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

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }

    /**
     * Allowlist de ordenamiento (H-04). Obligatorio: HasPaginationRules lo declara abstracto.
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'started_at', 'finished_at', 'status', 'scope', 'anomalies_found', 'created_at'];
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

    public function messages(): array
    {
        return array_merge($this->dateRangeMessages(), $this->paginationMessages());
    }

    public function attributes(): array
    {
        return array_merge(
            $this->dateRangeAttributes(),
            $this->paginationAttributes(),
            ['scope' => 'alcance', 'run_type' => 'tipo', 'status' => 'estado'],
        );
    }
}
