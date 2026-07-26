<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * H-05. `users` tiene UQ(business_id, email): el correo es único POR NEGOCIO,
 * no globalmente. Autenticar solo con correo devolvería una fila arbitraria
 * entre tenants. RF-01-02 exige resolver primero el negocio.
 *
 * NO extiende BaseTenantRequest: aquí todavía no hay sesión ni business_id.
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_slug' => ['required', 'string', 'max:160'],
            'email'         => ['required', 'string', 'email:rfc', 'max:180'],
            'password'      => ['required', 'string'],
            'remember'      => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_slug.required' => 'Debe indicar el negocio al que desea acceder.',
            'business_slug.string'   => 'El identificador del negocio no es válido.',
            'business_slug.max'      => 'El identificador del negocio no puede exceder los 160 caracteres.',
            'email.required'         => 'El correo electrónico es obligatorio.',
            'email.email'            => 'El correo electrónico no tiene un formato válido.',
            'email.max'              => 'El correo electrónico no puede exceder los 180 caracteres.',
            'password.required'      => 'La contraseña es obligatoria.',
            'remember.boolean'       => 'El indicador de sesión persistente debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business_slug' => 'negocio',
            'email'         => 'correo electrónico',
            'password'      => 'contraseña',
            'remember'      => 'recordar sesión',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'business_slug' => is_string($this->business_slug)
                ? mb_strtolower(trim($this->business_slug))
                : $this->business_slug,
            'email' => is_string($this->email)
                ? mb_strtolower(trim($this->email))
                : $this->email,
        ]);
    }
}
