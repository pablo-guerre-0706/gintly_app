<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\KpiSnapshot */
final class KpiSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'kpi_code'        => $this->kpi_code,
            'label'           => $this->label(),   // Registro canónico (config/kpis.php).
            'family'          => $this->family(),
            'unit'            => $this->unit(),
            'period_type'     => $this->period_type->value,
            'period_start'    => $this->period_start?->toDateString(),
            'period_end'      => $this->period_end?->toDateString(),
            'branch_id'       => $this->branch_id,
            'value'           => (string) $this->value,
            'target_value'    => $this->target_value !== null ? (string) $this->target_value : null,
            'achievement_pct' => $this->achievement_pct !== null ? (string) $this->achievement_pct : null,
            'metadata'        => $this->metadata,
            'calculated_at'   => $this->calculated_at?->toIso8601String(),
        ];
    }
}
