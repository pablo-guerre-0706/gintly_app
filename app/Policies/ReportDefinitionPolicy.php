<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ReportDefinition;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class ReportDefinitionPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    public function update(User $user, ReportDefinition $definition): bool
    {
        return $this->sharesBusinessWith($user, $definition)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    public function delete(User $user, ReportDefinition $definition): bool
    {
        return $this->sharesBusinessWith($user, $definition)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** GET /reports/{type} — reportes directivos (ROL-01). */
    public function generateReport(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Owner);
    }
}
