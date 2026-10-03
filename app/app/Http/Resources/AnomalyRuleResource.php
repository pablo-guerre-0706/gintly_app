<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AnomalyRule */
final class AnomalyRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code->value,
            'code_label'       => $this->code->label(),
            'name'             => $this->name,
            'threshold_value'  => $this->threshold_value !== null ? (string) $this->threshold_value : null,
            'threshold_type'   => $this->threshold_type->value,
            'default_severity' => $this->default_severity->value,
            'is_active'        => (bool) $this->is_active,
        ];
    }
}
