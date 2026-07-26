<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\BaseTenantRequest;
use App\Models\User;
use Illuminate\Validation\Validator;

final class UpdateUserRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->can('update', $target instanceof User ? $target : User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `sometimes` habilita la actualización parcial: si la clave no
            // viene, no se valida. `required` impide que, si vino, llegue vacía.
            'name' => ['sometimes', 'required', 'string', 'max:150'],

            'branch_id' => [
                'sometimes',
                'nullable',
                'integer',
                $this->tenantExists('branches'),
            ],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Invariante: nadie se desactiva a sí mismo. Sin esta guarda, el único
     * ROL-02 del negocio puede autobloquearse y dejarlo sin administrador.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('is_active') || $this->boolean('is_active')) {
                    return;
                }

                if ($this->targetsSelf()) {
                    $validator->errors()->add(
                        'is_active',
                        'No puede desactivar su propia cuenta. Solicite la operación a otro usuario autorizado.'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'     => 'El nombre no puede quedar vacío.',
            'name.max'          => 'El nombre no puede exceder los 150 caracteres.',
            'branch_id.integer' => 'La sucursal seleccionada no es válida.',
            'branch_id.exists'  => 'La sucursal seleccionada no existe o no pertenece a su negocio.',
            'is_active.boolean' => 'El estado del usuario debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'      => 'nombre',
            'branch_id' => 'sucursal',
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