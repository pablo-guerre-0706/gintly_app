<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;


final class AuthService
{
    private ?string $decoyHash = null;

    public function __construct(
        private readonly PermissionRegistrar $permissions,
    ) {}

    public function login(string $businessSlug, string $email, string $password): User
    {
        // business_slug (request) -> columna física businesses.slug. Business es raíz del tenant: sin BusinessScope.
        $business = Business::query()->where('slug', $businessSlug)->first();

        app(PermissionRegistrar::class)->setPermissionsTeamId($business?->id);

        $user = $business !== null
            ? User::query()->where('business_id', $business->id)->where('email', $email)->first()
            : null;

        $passwordValid = Hash::check($password, $user?->password ?? $this->decoyHash());
 
        $authorized = $business !== null
            && $business->status->canOperate()
            && $user !== null
            && $user->is_active            // estado del usuario es el booleano is_active
            && $passwordValid
            && $user->roles()->exists();   // sin rol no autentica
 
        if (! $authorized) {
            // 401 agnóstico. No revela si falló negocio, estado, correo, clave o rol.
            throw new AuthenticationException('Las credenciales proporcionadas no son válidas.');
        }
 
        // D6: establece sesión en guard web y dispara Illuminate\Auth\Events\Login -> listener escribe last_login_at.
        Auth::guard('web')->login($user);

        return $user;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }

    private function decoyHash(): string
    {
        // Hash bcrypt real y efímero; su único fin es igualar el costo temporal en la rama sin usuario.
        return $this->decoyHash ??= Hash::make(Str::random(40));
    }
}
