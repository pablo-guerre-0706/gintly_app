<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'is_active'     => (bool) $this->is_active,        // D6: estado del usuario es booleano
            'branch_id'     => $this->branch_id,
            // RF-01-01: exactamente un rol activo. Solo aparece si se eager-loadea 'roles' (evita N+1).
            'role'          => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->first()),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
            // business_id, password y remember_token NUNCA se exponen (aislamiento + secretos).
        ];
    }
}
