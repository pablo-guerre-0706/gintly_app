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

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las facturas.');
    }

    public function view(User $actor, Invoice $invoice): Response
    {
        if (! $this->sharesBusinessWith($actor, $invoice)) {
            return Response::deny('La factura solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta factura.');
    }

    // Emitir factura: el cajero que cierra la venta.
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
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
    public function viewDeliveryStatus(User $user, Invoice $invoice): bool
    {
        return $this->sharesBusinessWith($user, $invoice)
            && $this->hasAtLeast($user, RoleName::Operator);
    }
}
