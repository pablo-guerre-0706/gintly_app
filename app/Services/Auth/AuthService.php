<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Business;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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
        private readonly AuditLogger $audit,
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
 
        // D6: establece la sesión en el guard web.
        Auth::guard('web')->login($user);

        // RF-01: `last_login_at` es evidencia de acceso (detección de omisiones).
        // Se persiste en el único punto de entrada de la autenticación. saveQuietly
        // evita disparar eventos de modelo; forceFill omite la lista $fillable.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // RF-01-03: el inicio de sesión queda en la bitácora inmutable.
        $this->audit->record('login', $user, actor: $user);

        return $user;
    }

    public function logout(): void
    {
        // El actor debe capturarse ANTES de invalidar la sesión.
        $user = Auth::guard('web')->user();

        if ($user instanceof User) {
            $this->audit->record('logout', $user, actor: $user);
        }

        Auth::guard('web')->logout();
    }

    private function decoyHash(): string
    {
        // Hash bcrypt real y efímero; su único fin es igualar el costo temporal en la rama sin usuario.
        return $this->decoyHash ??= Hash::make(Str::random(40));
    }
}
