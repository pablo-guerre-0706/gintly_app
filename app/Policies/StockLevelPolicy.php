<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\StockLevel;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class StockLevelPolicy
{
    use InteractsWithTenant;

    // Lectura ROL-03: exige perfil BODEGUERO con inventario.ver. ROL-01/ROL-02 por nivel.
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'inventario.ver')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar el inventario.');
    }

    public function view(User $actor, StockLevel $stockLevel): Response
    {
        if (! $this->sharesBusinessWith($actor, $stockLevel)) {
            return Response::deny('El saldo solicitado no pertenece a su negocio.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'inventario.ver')) {
            return Response::deny('No tiene autorización para consultar este saldo.');
        }

        // ROL-03: solo saldos de una bodega que tenga asignada (aislamiento por bodega, no solo por sucursal).
        return $this->operatorOperatesWarehouse($actor, (int) $stockLevel->warehouse_id)
            ? Response::allow()
            : Response::deny('El saldo pertenece a una bodega que no tiene asignada.');
    }

    /**
     * MOD-03 (microcierre) · Consulta de DISPONIBILIDAD para FACTURAR. Habilita al facturador (ROL-03 con
     * ventas.crear o facturas.crear) y a ROL-01/ROL-02. NO exige inventario.ver ni concede acceso general a
     * bodegas o costos: es una lectura acotada de existencia/reservado/disponible de la bodega predeterminada.
     */
    public function viewForSelling(User $actor): Response
    {
        if (! $this->hasAtLeast($actor, RoleName::Operator)) {
            return Response::deny('No tiene autorización para consultar disponibilidad.');
        }

        if (! $this->isOperator($actor)) {
            return Response::allow(); // ROL-01/ROL-02 por nivel.
        }

        return $this->operatorGrants($actor, 'ventas.crear') || $this->operatorGrants($actor, 'facturas.crear')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar disponibilidad para facturar.');
    }

    // Solo edición de umbrales min/max (H-27). quantity/reserved no por API.
    public function updateThresholds(User $actor, StockLevel $stockLevel): Response
    {
        if (! $this->sharesBusinessWith($actor, $stockLevel)) {
            return Response::deny('El saldo indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para modificar los umbrales de inventario.');
    }
}
