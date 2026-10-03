<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 *
 * credit_limit sale como string (cast decimal:2), bcmath-safe.
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'document_type'      => $this->document_type->value,
            'document_type_label' => $this->document_type->label(),
            'document_number'    => $this->document_number,
            'email'              => $this->email,
            'phone_number'       => $this->phone_number,
            'birth_date'         => $this->birth_date?->toDateString(),
            'is_generic'         => $this->is_generic,
            'is_active'          => $this->is_active,
            'credit_limit'       => $this->credit_limit,
            'notes'              => $this->notes,
            'addresses'          => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
