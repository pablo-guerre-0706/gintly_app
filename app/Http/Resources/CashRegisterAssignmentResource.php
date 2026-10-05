<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashRegisterAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashRegisterAssignment
 *
 * MOD-06 · Asignación Caja–Cajero. `active` deriva de ended_at (null ⇒ vigente). Las relaciones se
 * exponen whenLoaded para no forzar consultas.
 */
final class CashRegisterAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'cash_register_id' => $this->cash_register_id,
            'user_id'          => $this->user_id,
            'branch_id'        => $this->branch_id,
            'assigned_by'      => $this->assigned_by,
            'assigned_at'      => $this->assigned_at?->toIso8601String(),
            'ended_at'         => $this->ended_at?->toIso8601String(),
            'ended_by'         => $this->ended_by,
            'active'           => $this->isActive(),
            'cash_register'    => new CashRegisterResource($this->whenLoaded('cashRegister')),
            'user'             => new UserResource($this->whenLoaded('user')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
