<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\BaseTenantRequest;
use App\Models\BusinessGoal;
use Illuminate\Validation\Validator;

final class UpdateBusinessGoalRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // BusinessGoalPolicy::update.
    }

    public function rules(): array
    {
        // La identidad (kpi_code, period_type, period_start, ámbito) es INMUTABLE: cambiarla sería
        // otra meta y rompería la unicidad histórica. Solo se ajusta el objetivo y el fin del período.
        return [
            'target_value' => ['sometimes', 'numeric', 'decimal:0,2', 'gt:0'],
            'period_end'   => ['sometimes', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $goal = $this->route('businessGoal');

                // period_end no puede ser anterior al inicio congelado de la meta (coherencia real).
                if ($goal instanceof BusinessGoal && $this->filled('period_end')) {
                    $start = $goal->period_start?->toDateString();
                    if ($start !== null && $this->input('period_end') < $start) {
                        $validator->errors()->add('period_end', 'El fin del período no puede ser anterior a su inicio.');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['target_value' => 'meta', 'period_end' => 'fin del período'];
    }
}
