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

    /** POST /accounts-receivable/{id}/payments — registrar abono (ROL-03+). */
    public function pay(User $user, AccountReceivable $accountReceivable): bool
    {
        return $this->sharesBusinessWith($user, $accountReceivable)
            && $this->hasAtLeast($user, RoleName::Operator);
    }

    // Sin create/update/delete: la CxC solo la orquesta InvoiceService (facturar/anular).
}
