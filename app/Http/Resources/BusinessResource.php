<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Business */
final class BusinessResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'plan'       => $this->plan,
            'status'     => $this->status?->value,   // BusinessStatus (backed enum) -> string
            'tax_rate'   => (string) $this->tax_rate, // decimal(5,4) bcmath -> string, nunca float
            'timezone'   => $this->timezone,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
