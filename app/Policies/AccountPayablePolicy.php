<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AccountPayable;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class AccountPayablePolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las cuentas por pagar.');
    }

    public function view(User $actor, AccountPayable $payable): Response
    {
        if (! $this->sharesBusinessWith($actor, $payable)) {
            return Response::deny('La cuenta solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta cuenta por pagar.');
    }

    // Registrar un pago es gestión de ROL-02.
    public function pay(User $actor, AccountPayable $payable): Response
    {
        if (! $this->sharesBusinessWith($actor, $payable)) {
            return Response::deny('La cuenta indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar pagos a proveedores.');
    }

    // Descongelar una CxP es potestad EXCLUSIVA de ROL-01.
    public function unblock(User $actor, AccountPayable $payable): Response
    {
        if (! $this->sharesBusinessWith($actor, $payable)) {
            return Response::deny('La cuenta indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('Solo el propietario puede descongelar una cuenta por pagar.');
    }
}
