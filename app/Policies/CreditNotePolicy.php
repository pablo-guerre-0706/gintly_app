<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CreditNote;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class CreditNotePolicy
{
    use InteractsWithTenant;

    /** GET /credit-notes (ROL-02+). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Admin);
    }

    /** GET /credit-notes/{id} (ROL-02+). */
    public function view(User $user, CreditNote $creditNote): bool
    {
        return $this->sharesBusinessWith($user, $creditNote)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /**
     * Autoridad de reembolso en efectivo (BR-06): potestad exclusiva de ROL-01.
     * La resolución se determina en runtime dentro del ReturnService, que consulta esta capacidad.
     */
    public function refundCash(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Owner);
    }
}
