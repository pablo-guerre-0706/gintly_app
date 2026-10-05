<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SupplierLocation;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Supplier;
use App\Models\SupplierLocation;

/**
 * MOD-04 · Edición de ubicación. Cambiar la dirección INVALIDA la confirmación anterior (lo aplica el
 * Service). No admite fijar coordenadas aquí: eso es geocodificar o confirmar manualmente.
 */
final class UpdateSupplierLocationRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            && ($this->user()?->can('manage', [SupplierLocation::class, $supplier]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'address'    => ['sometimes', 'required', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address.required' => 'La dirección no puede quedar vacía.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->address)) {
            $this->merge(['address' => trim($this->address)]);
        }
    }
}
