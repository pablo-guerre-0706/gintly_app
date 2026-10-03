<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashRegister
 */
final class CashRegisterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'branch_id'  => $this->branch_id,
            'name'       => $this->name,
            'is_active'  => $this->is_active,
            'branch'     => new BranchResource($this->whenLoaded('branch')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
