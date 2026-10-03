<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashRegister;

use App\Http\Requests\BaseTenantRequest;
use App\Models\CashRegister;
use Illuminate\Validation\Rule;

final class StoreCashRegisterRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashRegister::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', $this->tenantExists('branches')],

            'name' => [
                'required',
                'string',
                'max:100',
                // UQ(business_id, branch_id, name) plano con softDeletes. Se
                // replica el índice del motor, incluyendo whereNull(deleted_at).
                Rule::unique('cash_registers', 'name')
                    ->where('business_id', $this->businessId())
                    ->where('branch_id', $this->input('branch_id'))
                    ->whereNull('deleted_at'),
            ],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'La sucursal es obligatoria.',
            'branch_id.exists'   => 'La sucursal no existe o no pertenece a su negocio.',
            'name.required'      => 'El nombre de la caja es obligatorio.',
            'name.max'           => 'El nombre no puede exceder los 100 caracteres.',
            'name.unique'        => 'Ya existe una caja con este nombre en la sucursal.',
            'is_active.boolean'  => 'El estado de la caja debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'sucursal',
            'name'      => 'nombre',
            'is_active' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}

