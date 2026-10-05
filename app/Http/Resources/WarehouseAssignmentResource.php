<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WarehouseAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WarehouseAssignment
 *
 * MOD-03 · Asignación Bodega–Bodeguero. `active` deriva de ended_at (null ⇒ vigente).
 */
final class WarehouseAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'user_id'      => $this->user_id,
            'branch_id'    => $this->branch_id,
            'assigned_by'  => $this->assigned_by,
            'assigned_at'  => $this->assigned_at?->toIso8601String(),
            'ended_at'     => $this->ended_at?->toIso8601String(),
            'ended_by'     => $this->ended_by,
            'active'       => $this->isActive(),
            'warehouse'    => new WarehouseResource($this->whenLoaded('warehouse')),
            'user'         => new UserResource($this->whenLoaded('user')),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
