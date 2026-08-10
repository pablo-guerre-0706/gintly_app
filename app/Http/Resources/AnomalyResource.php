<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Anomaly */
final class AnomalyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'severity'              => $this->severity->value,
            'severity_label'        => $this->severity->label(),
            'status'                => $this->status->value,
            'status_label'          => $this->status->label(),
            'branch_id'             => $this->branch_id,
            'reconciliation_run_id' => $this->reconciliation_run_id,
            'expected_value'        => $this->expected_value !== null ? (string) $this->expected_value : null,
            'actual_value'          => $this->actual_value !== null ? (string) $this->actual_value : null,
            'difference'            => $this->difference !== null ? (string) $this->difference : null,
            'source_type'           => $this->source_type,
            'source_id'             => $this->source_id,
            'resolved_by'           => $this->resolved_by,
            'resolved_at'           => $this->resolved_at?->toIso8601String(),
            'detected_at'           => $this->detected_at?->toIso8601String(),

            'rule'   => new AnomalyRuleResource($this->whenLoaded('rule')),
            'events' => AnomalyEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
