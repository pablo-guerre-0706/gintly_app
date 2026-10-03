<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\OperativeProfile;
use App\Http\Requests\BaseTenantRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Fase 3 · Asigna/modifica el conjunto de perfiles operativos de un usuario ROL-03.
 * Reemplaza el conjunto completo (PUT). Debe conservar al menos un perfil: para dejar sin perfiles
 * se cambia el rol a uno no operativo (que los limpia). La autorización (rango + que el objetivo sea
 * ROL-03 del mismo negocio) vive en UserPolicy::manageProfiles.
 */
final class AssignProfilesRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->can('manageProfiles', $target instanceof User ? $target : User::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'profiles'   => ['required', 'array', 'min:1'],
            'profiles.*' => ['string', 'distinct', Rule::in(OperativeProfile::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'profiles.required' => 'Debe indicar al menos un perfil operativo.',
            'profiles.min'      => 'Un usuario operativo debe conservar al menos un perfil.',
            'profiles.*.in'     => 'Perfil operativo no reconocido.',
            'profiles.*.distinct' => 'No repita el mismo perfil.',
        ];
    }
}
