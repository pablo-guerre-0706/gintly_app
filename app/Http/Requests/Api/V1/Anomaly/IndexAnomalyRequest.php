<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Anomaly;

use App\Enums\AnomalyRuleCode;
use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexAnomalyRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // AnomalyPolicy::viewAny.
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }

    /**
     * Allowlist de ordenamiento (H-04). Obligatorio: HasPaginationRules lo declara abstracto
     * (sin él la clase ni siquiera carga). Solo columnas realmente consultables de anomalies.
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'detected_at', 'severity', 'status', 'difference', 'created_at'];
    }

    public function rules(): array
    {
        return array_merge(
            [
                'status'      => ['sometimes', 'string', Rule::in(AnomalyStatus::values())],
                'severity'    => ['sometimes', 'string', Rule::in(AnomalySeverity::values())],
                'rule_code'   => ['sometimes', 'string', Rule::in(AnomalyRuleCode::values())],
                'branch_id'   => ['sometimes', 'integer', $this->tenantExists('branches')], // soft-deletable
                'source_type' => ['sometimes', 'string', 'max:60'],
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
            [
                'status'      => 'estado',
                'severity'    => 'severidad',
                'rule_code'   => 'regla',
                'branch_id'   => 'sucursal',
                'source_type' => 'tipo de origen',
            ],
        );
    }
}
