<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
final class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'tax_id'       => $this->tax_id,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'status'       => $this->status->value,
            'status_label' => $this->status->label(),
            'approved_by'  => $this->approved_by,
            'approved_at'  => $this->approved_at?->toIso8601String(),
            'is_active'    => $this->is_active,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
