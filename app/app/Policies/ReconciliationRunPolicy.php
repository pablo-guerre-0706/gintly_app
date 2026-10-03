<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class ReconciliationRunPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    public function view(User $user, ReconciliationRun $run): bool
    {
        return $this->sharesBusinessWith($user, $run)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** POST /reconciliation-runs — conciliación manual (ROL-02+). */
    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }
}