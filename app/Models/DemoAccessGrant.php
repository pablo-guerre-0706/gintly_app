<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Explicit evaluation entitlement, NOT a subscription, payment or provider event. CLI-managed only. */
final class DemoAccessGrant extends Model
{
    protected $fillable = ['business_id', 'plan_key', 'reason', 'starts_at', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isCurrent(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->revoked_at === null && $this->starts_at !== null && $this->expires_at !== null
            && $this->starts_at->lessThanOrEqualTo($now) && $now->lessThan($this->expires_at);
    }
}
