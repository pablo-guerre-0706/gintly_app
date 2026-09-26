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
        return ['id', 'kpi_code', 'period_type', 'period_start', 'value', 'achievement_pct', 'calculated_at'];
    }

    public function rules(): array
    {
        return array_merge(
            [
                // Allowlist: solo códigos del registro canónico (config/kpis.php).
                'kpi_code'     => ['sometimes', 'string', Rule::in(array_keys((array) config('kpis')))],
                'period_type'  => ['sometimes', 'string', Rule::in(PeriodType::values())],
                'period_start' => ['sometimes', 'date'],
                'branch_id'    => ['sometimes', 'integer', $this->tenantExists('branches')],
            ],
            $this->paginationRules(),
        );
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }
}
