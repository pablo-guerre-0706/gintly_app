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

    public function rules(): array
    {
        return array_merge(
            [
                'status'      => ['sometimes', 'string', Rule::in(AnomalyStatus::values())],
                'severity'    => ['sometimes', 'string', Rule::in(AnomalySeverity::values())],
                'rule_code'   => ['sometimes', 'string', Rule::in(AnomalyRuleCode::values())],
                'branch_id'   => ['sometimes', 'integer', $this->tenantExists('branches')],
                'source_type' => ['sometimes', 'string', 'max:60'],
            ],
            $this->dateRangeRules(),
            $this->paginationRules(),
        );
    }

    public function attributes(): array
    {
        return [
            'status'      => 'estado',
            'severity'    => 'severidad',
            'rule_code'   => 'regla',
            'branch_id'   => 'sucursal',
            'source_type' => 'tipo de origen',
        ];
    }
}