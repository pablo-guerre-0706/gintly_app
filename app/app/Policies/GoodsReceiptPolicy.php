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

    // Lectura ROL-03: exige perfil BODEGUERO con compras.ver. ROL-01/ROL-02 por nivel.
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'compras.ver')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las recepciones.');
    }

    public function view(User $actor, GoodsReceipt $receipt): Response
    {
        if (! $this->sharesBusinessWith($actor, $receipt)) {
            return Response::deny('La recepción solicitada no pertenece a su negocio.');
        }

        // ROL-01/ROL-02: alcance de negocio. ROL-03 (bodeguero, compras.ver): solo su sucursal (por la bodega).
        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'compras.ver')) {
            return Response::deny('No tiene autorización para consultar esta recepción.');
        }

        return $this->operatorInBranch($actor, $receipt->warehouse?->branch_id)
            ? Response::allow()
            : Response::deny('La recepción pertenece a otra sucursal.');
    }

    // La recepción física la registra ROL-03 con perfil BODEGUERO (la sucursal la valida el servicio).
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'compras.recibir')
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
