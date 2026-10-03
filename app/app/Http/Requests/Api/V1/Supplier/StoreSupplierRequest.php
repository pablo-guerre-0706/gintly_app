<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Supplier;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Supplier;
use Illuminate\Validation\Rule;


// Nace 'pendiente'. status, approved_by, approved_at NUNCA por request.
final class StoreSupplierRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Supplier::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],

            // H-37 · tax_id nullable con NULL múltiples. El candado parcial
            // (tax_id_lock) solo aplica cuando hay RUC y la fila está activa.
            'tax_id' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('suppliers', 'tax_id')
                    ->where('business_id', $this->businessId())
                    ->whereNull('deleted_at'),
            ],

            'email' => ['nullable', 'string', 'email:rfc', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'name.max'      => 'El nombre no puede exceder los 160 caracteres.',
            'tax_id.max'    => 'La identificación fiscal no puede exceder los 30 caracteres.',
            'tax_id.unique' => 'Ya existe un proveedor activo con esta identificación fiscal en el negocio.',
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

        // tax_id en blanco se normaliza a null: un string vacío rompería el
        // candado (dos vacíos colisionarían); null admite múltiples.
        if (is_string($this->tax_id)) {
            $trimmed = trim($this->tax_id);
            $merge['tax_id'] = $trimmed === '' ? null : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
