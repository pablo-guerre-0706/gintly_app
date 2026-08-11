<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class KpiSnapshotPolicy
{
    use InteractsWithTenant;

    /** GET /kpi-snapshots (ROL-02+). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    /** GET /dashboard/kpis (ROL-01). */
    public function viewDashboard(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Owner);
    }

    /** POST /kpi-snapshots/recalculate (ROL-01). */
    public function recalculate(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Owner);
    }
}
