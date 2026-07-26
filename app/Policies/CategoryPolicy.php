<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class CategoryPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar el catálogo de categorías.');
    }

    public function view(User $actor, Category $category): Response
    {
        if (! $this->sharesBusinessWith($actor, $category)) {
            return Response::deny('La categoría solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta categoría.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar categorías.');
    }

    public function update(User $actor, Category $category): Response
    {
        return $this->manage($actor, $category, 'modificar');
    }

    public function delete(User $actor, Category $category): Response
    {
        return $this->manage($actor, $category, 'dar de baja');
    }

    private function manage(User $actor, Category $category, string $verbo): Response
    {
        if (! $this->sharesBusinessWith($actor, $category)) {
            return Response::deny('La categoría indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny("No tiene autorización para {$verbo} categorías.");
    }
}
