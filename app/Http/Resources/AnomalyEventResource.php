<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AnomalyEvent */
final class AnomalyEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'anomaly_id'  => $this->anomaly_id,
            'user_id'     => $this->user_id,
            'from_status' => $this->from_status,
            'to_status'   => $this->to_status,
            'comment'     => $this->comment,
            'changed_at'  => $this->changed_at?->toIso8601String(),
            'user'        => new UserResource($this->whenLoaded('user')),
        ];
    }
}
