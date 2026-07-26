<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\BaseTenantRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateUserRoleRequest extends BaseTenantRequest
{
    // Impide que un usuario asigne a otro un rol con más poder que el suyo para evitar hackeos.
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->can('assignRole', [
            $target instanceof User ? $target : User::class,
            $this->input('role'),
        ]) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                'max:255',
                Rule::exists('roles', 'name')->where(function ($query): void {
                    $query->where('guard_name', 'web')
                        ->where(fn ($q) => $q
                            ->where('business_id', $this->businessId())
                            ->orWhereNull('business_id'));
                }),
            ],
        ];
    }

    // Prohíbe que un usuario se cambie el rol a sí mismo para evitar que un administrador se
    // convierta en propietario ilegalmente.
     /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->targetsSelf()) {
                    $validator->errors()->add(
                        'role',
                        'No puede modificar su propio rol. La reasignación debe ejecutarla otro usuario autorizado.'
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
            'role.required' => 'Debe indicar el rol que se asignará al usuario.',
            'role.string'   => 'El rol debe expresarse como texto.',
            'role.max'      => 'El nombre del rol no puede exceder los 255 caracteres.',
            'role.exists'   => 'El rol seleccionado no existe o no está disponible para su negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'rol'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->role)) {
            $this->merge(['role' => trim($this->role)]);
        }
    }
}
