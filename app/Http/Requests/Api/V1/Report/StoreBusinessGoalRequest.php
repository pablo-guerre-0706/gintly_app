<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\BusinessGoalKpiCode;
use App\Enums\PeriodType;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class StoreBusinessGoalRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // BusinessGoalPolicy::create.
    }

    public function rules(): array
    {
        return [
            'kpi_code'     => ['required', 'string', Rule::in(BusinessGoalKpiCode::values())],
            'period_type'  => ['required', 'string', Rule::in(PeriodType::values())],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'target_value' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'branch_id'    => ['nullable', 'integer', $this->tenantExists('branches')],
        ];
        // La unicidad (negocio, sucursal, KPI, período, inicio) la valida el Service (GoalConflictException 422).
    }

    public function attributes(): array
    {
        return [
            'kpi_code'     => 'indicador',
            'period_type'  => 'tipo de período',
            'period_start' => 'inicio del período',
            'period_end'   => 'fin del período',
            'target_value' => 'meta',
            'branch_id'    => 'sucursal',
        ];
    }
}
