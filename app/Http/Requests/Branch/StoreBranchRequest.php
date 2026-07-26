<?php

declare(strict_types=1);

namespace App\Http\Requests\Branch;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Branch;

final class StoreBranchRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Branch::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                // H-09: respaldado por uniq_branch_active_name sobre name_lock.
                $this->tenantUnique('branches', 'name', excludeTrashed: true),
            ],

            'address' => ['required', 'string', 'max:255'],

            // H-06. El BRD exige responsable asignado como dato de acreditación.
            // La columna admite NULL solo porque su FK es SETNULL: eso describe
            // un estado válido en reposo, no una entrada válida.
            'manager_user_id' => [
                'required',
                'integer',
                $this->tenantExists('users')->where('is_active', true),
            ],

            // `date_format` y no `date`: `date` acepta "next tuesday" y "01/02/2026",
            // que Carbon interpreta según locale (1 de febrero o 2 de enero).
            'opened_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'             => 'El nombre de la sucursal es obligatorio.',
            'name.max'                  => 'El nombre no puede exceder los 150 caracteres.',
            'name.unique'               => 'Ya existe una sucursal activa con este nombre en el negocio.',
            'address.required'          => 'La dirección física es obligatoria para acreditar la sucursal.',
            'address.max'               => 'La dirección no puede exceder los 255 caracteres.',
            'manager_user_id.required'  => 'Debe designar un responsable; ninguna sucursal opera sin encargado asignado.',
            'manager_user_id.integer'   => 'El responsable seleccionado no es válido.',
            'manager_user_id.exists'    => 'El responsable seleccionado no existe, está inactivo o no pertenece a su negocio.',
            'opened_at.required'        => 'La fecha de apertura es obligatoria para acreditar la sucursal.',
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
