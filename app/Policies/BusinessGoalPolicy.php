<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\BusinessGoal;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class BusinessGoalPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    /** POST — fijar meta (ROL-01, BR-06). */
    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Owner);
    }

    public function update(User $user, BusinessGoal $goal): bool
    {
        return $this->sharesBusinessWith($user, $goal)
            && $this->hasAtLeast($user, RoleName::Owner);
    }

    public function delete(User $user, BusinessGoal $goal): bool
    {
        return $this->sharesBusinessWith($user, $goal)
            && $this->hasAtLeast($user, RoleName::Owner);
    }
}
