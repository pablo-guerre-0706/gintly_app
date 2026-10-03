<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Warehouse;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Warehouse;
use Illuminate\Validation\Rule;

final class StoreWarehouseRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Warehouse::class) ?? false;
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
                'max:120',
                // Candado name_lock (H-21): único por (negocio, sucursal, nombre
                // activo). Se acota además a la branch_id enviada para replicar
                // el alcance del índice compuesto.
                Rule::unique('warehouses', 'name')
                    ->where('business_id', $this->businessId())
                    ->where('branch_id', $this->input('branch_id'))
                    ->whereNull('deleted_at'),
            ],

            // La segunda default la rechaza default_lock en el motor; el request
            // solo valida forma. El servicio decide si degrada la anterior.
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
            'branch_id.required' => 'La sucursal es obligatoria.',
            'branch_id.exists'   => 'La sucursal seleccionada no existe o no pertenece a su negocio.',
            'name.required'      => 'El nombre de la bodega es obligatorio.',
            'name.max'           => 'El nombre no puede exceder los 120 caracteres.',
            'name.unique'        => 'Ya existe una bodega activa con este nombre en la sucursal.',
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
            'branch_id'  => 'sucursal',
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
