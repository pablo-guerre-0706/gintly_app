<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AnomalyRule;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class AnomalyRulePolicy
{
    use InteractsWithTenant;

    /** GET /anomaly-rules (ROL-02+). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    /** PUT /anomaly-rules/{id} — parametrizar umbral/severidad (ROL-01). */
    public function update(User $user, AnomalyRule $rule): bool
    {
        return $this->sharesBusinessWith($user, $rule)
            && $this->hasAtLeast($user, RoleName::Owner);
    }
}
