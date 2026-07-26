<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Api\V1\Concerns\VerifiesCurrentPassword;
use Illuminate\Validation\Rules\Password;

// Exigir contraseña actual confirma la identidad real del usuario y previene
// el secuestro de sesión
final class UpdateOwnPasswordRequest extends BaseTenantRequest
{
    use VerifiesCurrentPassword;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRule(),

            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::defaults(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required'         => 'Debe indicar su contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required'                 => 'Debe indicar la nueva contraseña.',
            'password.confirmed'                => 'La confirmación de la nueva contraseña no coincide.',
            'password.different'                => 'La nueva contraseña debe ser distinta de la actual.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'contraseña actual',
            'password'         => 'nueva contraseña',
        ];
    }
}
