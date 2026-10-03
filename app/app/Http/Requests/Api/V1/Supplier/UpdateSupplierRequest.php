<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Supplier;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

final class UpdateSupplierRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('supplier');

        return $this->user()?->can('update', $target instanceof Supplier ? $target : Supplier::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],

            'tax_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique('suppliers', 'tax_id')
                    ->where('business_id', $this->businessId())
                    ->whereNull('deleted_at')
                    ->ignore($this->routeId('supplier')),
            ],

            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del proveedor no puede quedar vacío.',
            'name.max'      => 'El nombre no puede exceder los 160 caracteres.',
            'tax_id.max'    => 'La identificación fiscal no puede exceder los 30 caracteres.',
            'tax_id.unique' => 'Ya existe otro proveedor activo con esta identificación fiscal en el negocio.',
            'email.email'   => 'El correo electrónico no tiene un formato válido.',
            'email.max'     => 'El correo electrónico no puede exceder los 180 caracteres.',
            'phone.max'     => 'El teléfono no puede exceder los 30 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'   => 'nombre',
            'tax_id' => 'identificación fiscal',
            'email'  => 'correo electrónico',
            'phone'  => 'teléfono',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->name)) {
            $merge['name'] = trim($this->name);
        }

        if (is_string($this->email)) {
            $merge['email'] = mb_strtolower(trim($this->email));
        }

        if (is_string($this->tax_id)) {
            $trimmed = trim($this->tax_id);
            $merge['tax_id'] = $trimmed === '' ? null : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
