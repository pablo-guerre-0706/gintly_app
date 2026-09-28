<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AccountReceivable;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class AccountReceivablePolicy
{
    use InteractsWithTenant; // before() fail-closed + hasAtLeast() + sharesBusinessWith().

    /** GET /accounts-receivable — cartera del negocio (ROL-02+). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    /**
     * Fase 7 · GET /accounts-receivable/collectible — consulta operativa de CxC cobrables.
     * Accesible para ROL-02+ y para ROL-03 con perfil CAJERO (cuentas_por_cobrar.ver). El alcance de
     * sucursal para ROL-03 lo aplica el controlador (solo su sucursal).
     */
    public function viewCollectible(User $user): bool
    {
        if ($this->hasAtLeast($user, RoleName::Admin)) {
            return true;
        }

        return $this->isOperator($user) && $this->operatorGrants($user, 'cuentas_por_cobrar.ver');
    }

    /** GET /accounts-receivable/{id} (ROL-02+). */
    public function view(User $user, AccountReceivable $accountReceivable): bool
    {
        return $this->sharesBusinessWith($user, $accountReceivable)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** GET /accounts-receivable/{id}/payments — historial de abonos (ROL-02+). */
    public function viewPayments(User $user, AccountReceivable $accountReceivable): bool
    {
        return $this->sharesBusinessWith($user, $accountReceivable)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /**
     * POST /accounts-receivable/{id}/payments — registrar abono (ROL-03+).
     * Fase 5: para ROL-03 exige el perfil CAJERO y que la factura asociada sea de SU sucursal.
     * (El cobro en EFECTIVO exige además sesión propia abierta: lo garantiza CashService.)
     */
    public function pay(User $user, AccountReceivable $accountReceivable): bool
    {
        if (! $this->sharesBusinessWith($user, $accountReceivable) || ! $this->hasAtLeast($user, RoleName::Operator)) {
            return false;
        }

        if (! $this->operatorGrants($user, 'cuentas_por_cobrar.abonar')) {
            return false;
        }

        return $this->operatorInBranch($user, $accountReceivable->invoice?->branch_id);
    }

    // Sin create/update/delete: la CxC solo la orquesta InvoiceService (facturar/anular).
}
