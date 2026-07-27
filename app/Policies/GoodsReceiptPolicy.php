<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class GoodsReceiptPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las recepciones.');
    }

    public function view(User $actor, GoodsReceipt $receipt): Response
    {
        if (! $this->sharesBusinessWith($actor, $receipt)) {
            return Response::deny('La recepción solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta recepción.');
    }

    // La recepción física la registra ROL-03, recibe en bodega.
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar recepciones.');
    }

    // Resolver una discrepancia es potestad EXCLUSIVA de ROL-01
    public function resolve(User $actor, GoodsReceipt $receipt): Response
    {
        if (! $this->sharesBusinessWith($actor, $receipt)) {
            return Response::deny('La recepción indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('Solo el propietario puede resolver una discrepancia de recepción.');
    }
}
