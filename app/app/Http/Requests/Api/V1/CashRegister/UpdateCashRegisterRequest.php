<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashRegister;

use App\Http\Requests\BaseTenantRequest;
use App\Models\CashRegister;
use Illuminate\Validation\Rule;

final class UpdateCashRegisterRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('cash_register');

        return $this->user()?->can('update', $target instanceof CashRegister ? $target : CashRegister::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $register = $this->route('cash_register');
        $branchId = $register instanceof CashRegister ? $register->branch_id : null;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('cash_registers', 'name')
                    ->where('business_id', $this->businessId())
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->ignore($this->routeId('cash_register')),
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
            'name.required'     => 'El nombre de la caja no puede quedar vacío.',
            'name.max'          => 'El nombre no puede exceder los 100 caracteres.',
            'name.unique'       => 'Ya existe otra caja con este nombre en la sucursal.',
            'is_active.boolean' => 'El estado de la caja debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
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
