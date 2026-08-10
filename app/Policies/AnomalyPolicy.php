<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Anomaly;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class AnomalyPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    public function view(User $user, Anomaly $anomaly): bool
    {
        return $this->sharesBusinessWith($user, $anomaly)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    public function viewEvents(User $user, Anomaly $anomaly): bool
    {
        return $this->sharesBusinessWith($user, $anomaly)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** POST /anomalies/{id}/justify (ROL-02). BR-01 lo refina el servicio. */
    public function justify(User $user, Anomaly $anomaly): bool
    {
        return $this->sharesBusinessWith($user, $anomaly)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /** POST /anomalies/{id}/resolve (ROL-01). */
    public function resolve(User $user, Anomaly $anomaly): bool
    {
        return $this->sharesBusinessWith($user, $anomaly)
            && $this->hasAtLeast($user, RoleName::Owner);
    }
}