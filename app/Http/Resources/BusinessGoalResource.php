<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BusinessGoal */
final class BusinessGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'kpi_code'     => $this->kpi_code->value,
            'kpi_label'    => $this->kpi_code->label(),
            'period_type'  => $this->period_type->value,
            'period_start' => $this->period_start?->toDateString(),
            'period_end'   => $this->period_end?->toDateString(),
            'target_value' => (string) $this->target_value,
            'branch_id'    => $this->branch_id,
            'created_by'   => $this->created_by,
        ];
    }
}
