<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReconciliationRun */
final class ReconciliationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'scope'           => $this->scope->value,
            'scope_label'     => $this->scope->label(),
            'run_type'        => $this->run_type->value,
            'status'          => $this->status->value,
            'status_label'    => $this->status->label(),
            'branch_id'       => $this->branch_id,
            'triggered_by'    => $this->triggered_by,
            'anomalies_found' => (int) $this->anomalies_found,
            'started_at'      => $this->started_at?->toIso8601String(),
            'finished_at'     => $this->finished_at?->toIso8601String(),

            'anomalies' => AnomalyResource::collection($this->whenLoaded('anomalies')),
        ];
    }
}
