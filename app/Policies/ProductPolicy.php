<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


final class ProductPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar el catálogo de productos.');
    }

    public function view(User $actor, Product $product): Response
    {
        if (! $this->sharesBusinessWith($actor, $product)) {
            return Response::deny('El producto solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este producto.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar productos.');
    }

    public function update(User $actor, Product $product): Response
    {
        return $this->manage($actor, $product, 'modificar');
    }

    public function delete(User $actor, Product $product): Response
    {
        return $this->manage($actor, $product, 'dar de baja');
    }

    // Receta es sub-recurso del producto compuesto: su autorización es gestionar el
    // propio producto. ProductRecipe no necesita Policy propia, se resuelva por el compuesto.
    public function manageRecipe(User $actor, Product $product): Response
    {
        return $this->manage($actor, $product, 'gestionar la receta de');
    }

    private function manage(User $actor, Product $product, string $verbo): Response
    {
        if (! $this->sharesBusinessWith($actor, $product)) {
            return Response::deny('El producto indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny("No tiene autorización para {$verbo} productos.");
    }
}
