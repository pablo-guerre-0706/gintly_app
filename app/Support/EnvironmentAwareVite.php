<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Vite;

/** Prevent a stale development pointer from bypassing the deployed manifest. */
final class EnvironmentAwareVite extends Vite
{
    public function __construct(private readonly Application $application) {}

    public function isRunningHot(): bool
    {
        return $this->application->environment('local') && parent::isRunningHot();
    }
}
