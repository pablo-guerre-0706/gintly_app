<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Api\V1\Concerns\VerifiesCurrentPassword;
use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;


// La reautentificación del administrador con su propia contraseña evita cambios 
// maliciosos de credenciales ajenas en cadena.
final class ResetUserPasswordRequest extends BaseTenantRequest
{
    use VerifiesCurrentPassword;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->can('resetPassword', $target instanceof User ? $target : User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRule(),

            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * El restablecimiento propio pertenece a /me/password, donde se verifica
     * la contraseña vigente del titular. Permitirlo aquí eludiría esa vía.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->targetsSelf()) {
                    $validator->errors()->add(
                        'password',
                        'Para cambiar su propia contraseña utilice la opción de cambio personal.'
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
            'current_password.required'         => 'Debe confirmar su propia contraseña para autorizar el restablecimiento.',
            'current_password.current_password' => 'Su contraseña no es correcta; el restablecimiento no fue autorizado.',
            'password.required'                 => 'Debe indicar la nueva contraseña del usuario.',
            'password.confirmed'                => 'La confirmación de la nueva contraseña no coincide.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'su contraseña',
            'password'         => 'nueva contraseña del usuario',
        ];
    }
}
