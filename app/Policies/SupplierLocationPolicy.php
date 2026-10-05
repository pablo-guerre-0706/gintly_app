<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

/**
 * MOD-04 · Ubicaciones de proveedor. Consulta (incluido el mapa): quien puede ver proveedores
 * (proveedores.ver). Alta/edición/geocodificación/confirmación del marcador: ROL-01/ROL-02 (dato maestro
 * administrativo). Los proveedores no se aíslan por sucursal; la compuerta es capacidad/rol + negocio.
 */
final class SupplierLocationPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor, Supplier $supplier): Response
    {
        if (! $this->sharesBusinessWith($actor, $supplier)) {
            return Response::denyWithStatus(404, 'El proveedor indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'proveedores.ver')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las ubicaciones del proveedor.');
    }

    /** Alta/edición/geocodificación/confirmación: potestad administrativa (ROL-01/ROL-02). */
    public function manage(User $actor, Supplier $supplier): Response
    {
        if (! $this->sharesBusinessWith($actor, $supplier)) {
            return Response::denyWithStatus(404, 'El proveedor indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para administrar las ubicaciones del proveedor.');
    }
}
