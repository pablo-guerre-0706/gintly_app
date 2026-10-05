<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SupplierLocation;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Supplier;
use App\Models\SupplierLocation;
use Illuminate\Validation\Validator;

/**
 * MOD-04 · Alta de ubicación de proveedor (ROL-01/ROL-02). Coordenadas opcionales (descubrimiento externo);
 * si se envía una, se exige la otra y en rango. La confirmación es un acto aparte (no se confirma al alta).
 */
final class StoreSupplierLocationRequest extends BaseTenantRequest
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
            'address'     => ['required', 'string', 'max:255'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'external_id' => ['nullable', 'string', 'max:120'],
            'is_primary'  => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $lat = $this->input('latitude');
            $lng = $this->input('longitude');
            if (($lat === null) !== ($lng === null)) {
                $validator->errors()->add('latitude', 'Debe indicar latitud y longitud juntas, o ninguna.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address.required'  => 'La dirección es obligatoria.',
            'latitude.between'  => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->address)) {
            $this->merge(['address' => trim($this->address)]);
        }
    }
}
