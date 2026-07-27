<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class SalePolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las ventas.');
    }

    public function view(User $actor, Sale $sale): Response
    {
        if (! $this->sharesBusinessWith($actor, $sale)) {
            return Response::deny('La venta solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta venta.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar ventas.');
    }

    /**
     * Agregar/quitar ítems y confirmar: el cajero que opera la venta.
     */
    public function manageItems(User $actor, Sale $sale): Response
    {
        if (! $this->sharesBusinessWith($actor, $sale)) {
            return Response::deny('La venta indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para modificar esta venta.');
    }

    public function confirm(User $actor, Sale $sale): Response
    {
        return $this->manageItems($actor, $sale);
    }
}
