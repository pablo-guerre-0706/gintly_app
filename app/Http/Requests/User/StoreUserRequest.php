<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\BaseTenantRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],

            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:180',
                // excludeTrashed: true exige el candado `email_lock`.
                // Sin la migración de la sección 17, cámbielo a false.
                $this->tenantUnique('users', 'email', excludeTrashed: true),
            ],

            'password' => ['required', 'string', 'confirmed', Password::defaults()],

            'branch_id' => [
                'nullable',
                'integer',
                $this->tenantExists('branches'),
            ],

            // RF-01-01: exactamente un rol activo. `roles` no tiene borrado
            // lógico; Spatie en modo teams admite roles del negocio y globales.
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'      => 'El nombre del usuario es obligatorio.',
            'name.max'           => 'El nombre no puede exceder los 150 caracteres.',
            'email.required'     => 'El correo electrónico es obligatorio.',
            'email.email'        => 'El correo electrónico no tiene un formato válido.',
            'email.max'          => 'El correo electrónico no puede exceder los 180 caracteres.',
            'email.unique'       => 'Ya existe un usuario activo con este correo electrónico en el negocio.',
            'password.required'  => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'branch_id.integer'  => 'La sucursal seleccionada no es válida.',
            'branch_id.exists'   => 'La sucursal seleccionada no existe o no pertenece a su negocio.',
            'role.required'      => 'Debe asignar un rol al usuario; un usuario sin rol no puede autenticarse.',
            'role.exists'        => 'El rol seleccionado no existe o no está disponible para su negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'      => 'nombre',
            'email'     => 'correo electrónico',
            'password'  => 'contraseña',
            'branch_id' => 'sucursal',
            'role'      => 'rol',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }
}
