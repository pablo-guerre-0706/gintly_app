<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class CashSessionPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator); // ROL-03
    }

    public function view(User $user, CashSession $session): bool
    {
        return $this->sharesBusinessWith($user, $session)
            && $this->hasAtLeast($user, RoleName::Operator);
    }

    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator); // abrir sesión: el cajero
    }

    public function close(User $user, CashSession $session): Response
    {
        if (! $this->sharesBusinessWith($user, $session)) {
            return Response::denyWithStatus(404);
        }

        $isOwner   = (int) $session->opened_by === (int) $user->id;
        $isAuditor = $this->hasAtLeast($user, RoleName::Admin);

        return $isOwner || $isAuditor
            ? Response::allow()
            : Response::denyWithStatus(403, 'Solo quien abrió la sesión o un Administrador puede cerrarla.');
    }
}
