<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Business;
use App\Models\DemoAccessGrant;
use Illuminate\Support\Carbon;

/** Server-configured enrollment only. Called inside the canonical registration transaction, never on replay. */
final class RegistrationEvaluationGrant
{
    public function __construct(private readonly CommercialAccess $access)
    {
    }

    public function grantToNewBusiness(Business $business): ?DemoAccessGrant
    {
        if (config('billing.demo_access.registration.enabled') !== true) {
            return null;
        }
        $starts = $this->timestamp(config('billing.demo_access.registration.starts_at'));
        $ends = $this->timestamp(config('billing.demo_access.registration.ends_at'));
        if (! $starts->lessThan($ends)) {
            throw new \LogicException('Invalid evaluation enrollment window.');
        }
        $now = Carbon::now('UTC');
        if ($now->lessThan($starts) || ! $now->lessThan($ends)) {
            return null; // Normal registration/checkout outside the half-open enrollment window.
        }
        $days = (string) config('billing.demo_access.registration.days');
        $plan = (string) config('billing.demo_access.registration.plan');
        if (config('billing.demo_access.multiple_businesses') !== true || ! $this->access->canGrantDemoTo($business)
            || ! preg_match('/^[1-9][0-9]*$/D', $days) || (int) $days > (int) config('billing.demo_access.max_days', 30)
            || ! array_key_exists($plan, (array) config('billing.catalog', []))) {
            // Bad enabled configuration must not silently claim a successful evaluation registration.
            throw new \LogicException('Invalid evaluation enrollment configuration.');
        }

        return DemoAccessGrant::query()->create([
            'business_id' => $business->id, 'plan_key' => $plan,
            'reason' => 'Canonical registration evaluation window',
            'starts_at' => $now, 'expires_at' => $now->copy()->addDays((int) $days), 'revoked_at' => null,
        ]);
    }

    private function timestamp(mixed $value): Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new \LogicException('Evaluation window requires explicit RFC3339 timestamps.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new \LogicException('Invalid evaluation window timestamp.');
        }

        return Carbon::instance($date)->utc();
    }
}
