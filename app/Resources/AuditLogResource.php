<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
final class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'action'         => $this->action,
            'auditable_type' => $this->auditable_type, // alias del morphMap (snake_case)
            'auditable_id'   => $this->auditable_id,
            'user_id'        => $this->user_id,
            'old_values'     => $this->old_values,      // json cast -> array|null
            'new_values'     => $this->new_values,
            'ip_address'     => $this->ip_address,
            'created_at'     => $this->created_at?->toIso8601String(),
            // Append-only: NO hay updated_at ni deleted_at.
        ];
    }
}
