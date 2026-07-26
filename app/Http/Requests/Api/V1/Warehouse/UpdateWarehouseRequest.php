<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Warehouse;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Warehouse;
use Illuminate\Validation\Rule;

final class UpdateWarehouseRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('warehouse');

        return $this->user()?->can('update', $target instanceof Warehouse ? $target : Warehouse::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');
        $branchId = $warehouse instanceof Warehouse ? $warehouse->branch_id : $this->input('branch_id');

        return [
            // branch_id no es editable: mover una bodega de sucursal reasignaría
            // todo su stock. Si viniera, se ignora (no está en fillable del modelo).
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('warehouses', 'name')
                    ->where('business_id', $this->businessId())
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->ignore($this->routeId('warehouse')),
            ],

            'is_default' => ['sometimes', 'boolean'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'      => 'El nombre de la bodega no puede quedar vacío.',
            'name.max'           => 'El nombre no puede exceder los 120 caracteres.',
            'name.unique'        => 'Ya existe otra bodega activa con este nombre en la sucursal.',
            'is_default.boolean' => 'El indicador de bodega predeterminada debe ser verdadero o falso.',
            'is_active.boolean'  => 'El estado de la bodega debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'       => 'nombre',
            'is_default' => 'bodega predeterminada',
            'is_active'  => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
