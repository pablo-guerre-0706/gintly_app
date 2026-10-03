<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReportDefinition */
final class ReportDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'report_type'   => $this->report_type,
            'filters'       => $this->filters,
            'is_scheduled'  => (bool) $this->is_scheduled,
            'schedule_cron' => $this->schedule_cron,
            'user_id'       => $this->user_id,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
