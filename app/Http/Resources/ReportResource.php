<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envuelve el array de ReportService::generar().
 * El controlador hace: new ReportResource($service->generar(...)).
 */
final class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type'        => $this->resource['type'],
            'period'      => $this->resource['period'],
            'totals'      => $this->resource['totals'],
            'comparisons' => $this->resource['comparisons'],
            'series'      => $this->resource['series'],
        ];
    }
}
