<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class SupplierPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar los proveedores.');
    }

    public function view(User $actor, Supplier $supplier): Response
    {
        if (! $this->sharesBusinessWith($actor, $supplier)) {
            return Response::deny('El proveedor solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este proveedor.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar proveedores.');
    }

    public function update(User $actor, Supplier $supplier): Response
    {
        return $this->manageAsAdmin($actor, $supplier, 'modificar');
    }

    public function delete(User $actor, Supplier $supplier): Response
    {
        return $this->manageAsAdmin($actor, $supplier, 'dar de baja');
    }

    // Aprobar y suspender es potestad EXCLUSIVA de ROL-01. El umbral es Owner, no Admin
    public function approve(User $actor, Supplier $supplier): Response
    {
        return $this->manageAsOwner($actor, $supplier, 'aprobar');
    }

    public function suspend(User $actor, Supplier $supplier): Response
    {
        return $this->manageAsOwner($actor, $supplier, 'suspender');
    }

    private function manageAsAdmin(User $actor, Supplier $supplier, string $verbo): Response
    {
        if (! $this->sharesBusinessWith($actor, $supplier)) {
            return Response::deny('El proveedor indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny("No tiene autorización para {$verbo} proveedores.");
    }

    private function manageAsOwner(User $actor, Supplier $supplier, string $verbo): Response
    {
        if (! $this->sharesBusinessWith($actor, $supplier)) {
            return Response::deny('El proveedor indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny("Solo el propietario puede {$verbo} proveedores.");
    }
}
