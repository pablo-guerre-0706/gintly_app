<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\PhysicalCount;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class PhysicalCountPolicy
{
    use InteractsWithTenant;

    // Lectura ROL-03: exige perfil BODEGUERO con inventario.conteo. ROL-01/ROL-02 por nivel.
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'inventario.conteo')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar los conteos físicos.');
    }

    public function view(User $actor, PhysicalCount $count): Response
    {
        if (! $this->sharesBusinessWith($actor, $count)) {
            return Response::deny('El conteo solicitado no pertenece a su negocio.');
        }

        // ROL-01/ROL-02: alcance de negocio. ROL-03 (bodeguero, inventario.conteo): solo su sucursal (por la bodega).
        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'inventario.conteo')) {
            return Response::deny('No tiene autorización para consultar este conteo.');
        }

        // ROL-03: solo conteos de una bodega que tenga asignada (aislamiento por bodega, no solo por sucursal).
        return $this->operatorOperatesWarehouse($actor, (int) $count->warehouse_id)
            ? Response::allow()
            : Response::deny('El conteo pertenece a una bodega que no tiene asignada.');
    }

    // El conteo lo registra el operativo (ROL-03) con perfil BODEGUERO; la bodega debe ser de su sucursal (servicio).
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'inventario.conteo')
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar conteos físicos.');
    }

    // Aplicar (ajustar stock) y justificar son decisiones de ROL-02.
    public function apply(User $actor, PhysicalCount $count): Response
    {
        return $this->resolve($actor, $count);
    }

    public function justify(User $actor, PhysicalCount $count): Response
    {
        return $this->resolve($actor, $count);
    }

    private function resolve(User $actor, PhysicalCount $count): Response
    {
        if (! $this->sharesBusinessWith($actor, $count)) {
            return Response::deny('El conteo indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo un administrador puede aplicar o justificar un conteo físico.');
    }
}
