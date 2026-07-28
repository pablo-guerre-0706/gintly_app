<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Dispatch;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class DispatchPolicy
{
    use InteractsWithTenant; // before() fail-closed + hasAtLeast() + sharesBusinessWith().

    /** GET /dispatches (ROL-03+). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator);
    }

    /** GET /dispatches/{id} (ROL-03+). */
    public function view(User $user, Dispatch $dispatch): bool
    {
        return $this->sharesBusinessWith($user, $dispatch)
            && $this->hasAtLeast($user, RoleName::Operator);
    }

    /** GET /dispatches/{id}/items (ROL-02+). */
    public function viewItems(User $user, Dispatch $dispatch): bool
    {
        return $this->sharesBusinessWith($user, $dispatch)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** POST /dispatches — registrar retiro (ROL-03). */
    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator);
    }

    /** POST /dispatches/{id}/revert — reversión (ROL-02, RF-09-04). */
    public function revert(User $user, Dispatch $dispatch): bool
    {
        return $this->sharesBusinessWith($user, $dispatch)
            && $this->hasAtLeast($user, RoleName::Admin);
    }
}
