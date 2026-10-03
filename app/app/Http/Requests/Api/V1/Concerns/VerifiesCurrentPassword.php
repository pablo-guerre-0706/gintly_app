<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;


trait VerifiesCurrentPassword
{
    protected function apiGuard(): string
    {
        return (string) config('gintly.tenant.api_guard', 'sanctum');
    }

    /**
     * Regla completa de la contraseña actual, ya con el guard resuelto.
     *
     * @return array<int, string>
     */
    protected function currentPasswordRule(): array
    {
        return ['required', 'string', 'current_password:'.$this->apiGuard()];
    }
}
