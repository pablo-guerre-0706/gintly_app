<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Brand;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class BrandPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las marcas.');
    }

    public function view(User $actor, Brand $brand): Response
    {
        if (! $this->sharesBusinessWith($actor, $brand)) {
            return Response::deny('La marca solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta marca.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar marcas.');
    }

    public function update(User $actor, Brand $brand): Response
    {
        return $this->manage($actor, $brand, 'modificar');
    }

    public function delete(User $actor, Brand $brand): Response
    {
        return $this->manage($actor, $brand, 'dar de baja');
    }

    private function manage(User $actor, Brand $brand, string $verbo): Response
    {
        if (! $this->sharesBusinessWith($actor, $brand)) {
            return Response::deny('La marca indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny("No tiene autorización para {$verbo} marcas.");
    }
}
