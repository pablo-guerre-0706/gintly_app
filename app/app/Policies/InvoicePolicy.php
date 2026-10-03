<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class InvoicePolicy
{
    use InteractsWithTenant;

    // Lectura ROL-03: exige perfil con facturas.ver (cajero/facturador/despachador). ROL-01/ROL-02 por nivel.
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'facturas.ver')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las facturas.');
    }

    public function view(User $actor, Invoice $invoice): Response
    {
        if (! $this->sharesBusinessWith($actor, $invoice)) {
            return Response::deny('La factura solicitada no pertenece a su negocio.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'facturas.ver')) {
            return Response::deny('No tiene autorización para consultar esta factura.');
        }

        // Fase 5: ROL-03 no consulta facturas de otra sucursal.
        return $this->operatorInBranch($actor, $invoice->branch_id)
            ? Response::allow()
            : Response::deny('La factura pertenece a otra sucursal.');
    }

    // Emitir factura: el FACTURADOR. Fase 5: para ROL-03 exige ese perfil.
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'facturas.crear')
            ? Response::allow()
            : Response::deny('No tiene autorización para emitir facturas.');
    }

    // El núcleo fiscal es inmutable: ninguna edición procede. Se declara denegado explícito
    public function update(User $actor, Invoice $invoice): Response
    {
        return Response::deny('El núcleo fiscal de una factura emitida es inmutable: no admite modificación.');
    }

    // Anular es potestad de ROL-01. El servicio verifica además que la factura esté emitida.
    public function void(User $actor, Invoice $invoice): Response
    {
        if (! $this->sharesBusinessWith($actor, $invoice)) {
            return Response::deny('La factura indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('Solo el propietario puede anular una factura.');
    }

    // --- MOD-09: saldo pendiente de entrega (ROL-03+, RF-09-02) ---
    // Fase 5: despachador (facturas.ver) y en su propia sucursal.
    public function viewDeliveryStatus(User $user, Invoice $invoice): bool
    {
        return $this->sharesBusinessWith($user, $invoice)
            && $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'facturas.ver')
            && $this->operatorInBranch($user, $invoice->branch_id);
    }
}
