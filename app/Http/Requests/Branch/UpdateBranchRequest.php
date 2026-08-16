<?php

declare(strict_types=1);

namespace App\Http\Requests\Branch;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Branch;

final class UpdateBranchRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                // ignore(): añade `AND id <> ?`. Sin él, guardar la sucursal
                // sin tocar el nombre falla porque el unique halla su propia fila.
                $this->tenantUnique('branches', 'name', excludeTrashed: true)
                    ->ignore($this->routeId('branch')),
            ],

            'address' => ['sometimes', 'required', 'string', 'max:255'],

            'manager_user_id' => [
                'sometimes',
                'required',
                'integer',
                $this->tenantExists('users')->where('is_active', true),
            ],

            'opened_at' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'             => 'El nombre de la sucursal no puede quedar vacío.',
            'name.max'                  => 'El nombre no puede exceder los 150 caracteres.',
            'name.unique'               => 'Ya existe otra sucursal activa con este nombre en el negocio.',
            'address.required'          => 'La dirección física no puede quedar vacía.',
            'address.max'               => 'La dirección no puede exceder los 255 caracteres.',
            'manager_user_id.required'  => 'La sucursal debe conservar un responsable asignado.',
            'manager_user_id.integer'   => 'El responsable seleccionado no es válido.',
            'manager_user_id.exists'    => 'El responsable seleccionado no existe, está inactivo o no pertenece a su negocio.',
            'opened_at.required'        => 'La fecha de apertura no puede quedar vacía.',
            'opened_at.date_format'     => 'La fecha de apertura debe expresarse en formato AAAA-MM-DD.',
            'opened_at.before_or_equal' => 'La fecha de apertura no puede ser posterior al día de hoy.',
            'is_active.boolean'         => 'El estado de la sucursal debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'            => 'nombre',
            'address'         => 'dirección',
            'manager_user_id' => 'responsable',
            'opened_at'       => 'fecha de apertura',
            'is_active'       => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'name'    => is_string($this->name) ? trim($this->name) : null,
            'address' => is_string($this->address) ? trim($this->address) : null,
        ], static fn ($value) => $value !== null));
    }
}
