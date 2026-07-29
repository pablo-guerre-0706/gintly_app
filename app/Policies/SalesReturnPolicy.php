<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\SalesReturn;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class SalesReturnPolicy
{
    use InteractsWithTenant;

    /** GET /sales-returns (ROL-02+). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    /** GET /sales-returns/{id} (ROL-02+). */
    public function view(User $user, SalesReturn $salesReturn): bool
    {
        return $this->sharesBusinessWith($user, $salesReturn)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** GET /sales-returns/{id}/items (ROL-02+). */
    public function viewItems(User $user, SalesReturn $salesReturn): bool
    {
        return $this->sharesBusinessWith($user, $salesReturn)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** POST /sales-returns — registrar devolución (ROL-03+). */
    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator);
    }
}
