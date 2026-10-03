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

    // Lectura ROL-03: exige el perfil con capacidad ventas.ver (facturador). ROL-01/ROL-02 por nivel.
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'ventas.ver')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las ventas.');
    }

    public function view(User $actor, Sale $sale): Response
    {
        if (! $this->sharesBusinessWith($actor, $sale)) {
            return Response::deny('La venta solicitada no pertenece a su negocio.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'ventas.ver')) {
            return Response::deny('No tiene autorización para consultar esta venta.');
        }

        // Fase 5: ROL-03 no consulta ventas de otra sucursal.
        return $this->operatorInBranch($actor, $sale->branch_id)
            ? Response::allow()
            : Response::deny('La venta pertenece a otra sucursal.');
    }

    public function create(User $actor): Response
    {
        // Fase 5: registrar ventas exige el perfil FACTURADOR para ROL-03.
        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'ventas.crear')) {
            return Response::deny('No tiene autorización para registrar ventas.');
        }

        return Response::allow();
    }

    /**
     * Agregar/quitar ítems y confirmar: el FACTURADOR que opera la venta, en SU sucursal.
     */
    public function manageItems(User $actor, Sale $sale): Response
    {
        if (! $this->sharesBusinessWith($actor, $sale)) {
            return Response::deny('La venta indicada no pertenece a su negocio.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'ventas.crear')) {
            return Response::deny('No tiene autorización para modificar esta venta.');
        }

        return $this->operatorInBranch($actor, $sale->branch_id)
            ? Response::allow()
            : Response::deny('La venta pertenece a otra sucursal.');
    }

    public function confirm(User $actor, Sale $sale): Response
    {
        return $this->manageItems($actor, $sale);
    }
}
