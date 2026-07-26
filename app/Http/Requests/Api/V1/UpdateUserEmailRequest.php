<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Api\V1\Concerns\VerifiesCurrentPassword;
use App\Models\User;


// Impide el robo de cuentas forzando al administrador a confirmar su
// identidad antes de cambiar un correo electrónico.
final class UpdateUserEmailRequest extends BaseTenantRequest
{
    use VerifiesCurrentPassword;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->can('updateEmail', $target instanceof User ? $target : User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRule(),

            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:180',
                // Candado parcial email_lock: excludeTrashed alineado con el
                // índice uniq_user_active_email. ignore() excluye la propia
                // fila para permitir reenviar el mismo correo sin cambios.
                $this->tenantUnique('users', 'email', excludeTrashed: true)
                    ->ignore($this->routeId('user')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required'         => 'Debe confirmar su propia contraseña para autorizar el cambio de correo.',
            'current_password.current_password' => 'Su contraseña no es correcta; el cambio no fue autorizado.',
            'email.required'                    => 'El correo electrónico es obligatorio.',
            'email.email'                       => 'El correo electrónico no tiene un formato válido.',
            'email.max'                         => 'El correo electrónico no puede exceder los 180 caracteres.',
            'email.unique'                      => 'Ya existe otro usuario activo con este correo electrónico en el negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'su contraseña',
            'email'            => 'correo electrónico',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }
}
