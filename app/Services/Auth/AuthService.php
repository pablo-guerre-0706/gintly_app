<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final class AuthService
{
    private ?string $decoyHash = null;

    public function __construct(
        private readonly PermissionRegistrar $permissions,
    ) {}

    public function login(string $businessSlug, string $email, string $password, string $ip): User
    {
        $throttleKey = $this->throttleKey($businessSlug, $email, $ip);
        $this->assertNotRateLimited($throttleKey);

        // business_slug (request) -> columna física businesses.slug. Business es raíz del tenant: sin BusinessScope.
        $business = Business::query()->where('slug', $businessSlug)->first();

        // Fija el team (Spatie) para que la verificación de rol resuelva por negocio. null es aceptable.
        $this->permissions->setPermissionsTeamId($business?->id);

        // User NO usa BelongsToBusiness -> el filtro por business_id es manual y obligatorio.
        $user = $business === null
            ? null
            : User::query()
                ->where('business_id', $business->id)
                ->where('email', $email)
                ->first();

        // Hash::check SIEMPRE se ejecuta (bcrypt real) para no filtrar la existencia del correo por análisis de tiempos.
        $passwordValid = Hash::check($password, $user?->password ?? $this->decoyHash());

        $authorized = $user !== null
            && $user->is_active            // estado del usuario es el booleano is_active
            && $passwordValid
            && $user->roles()->exists();   // sin rol no autentica

        if (! $authorized) {
            RateLimiter::hit($throttleKey, (int) config('gintly.auth.decay_seconds', 60));
            // D7: 401 agnóstico. Jamás revela si falló negocio, correo, clave, estado o rol.
            throw new AuthenticationException('Las credenciales proporcionadas no son válidas.');
        }

        RateLimiter::clear($throttleKey);

        // D6: establece sesión en guard web y dispara Illuminate\Auth\Events\Login -> listener escribe last_login_at.
        Auth::guard('web')->login($user);

        return $user;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }

    private function assertNotRateLimited(string $throttleKey): void
    {
        $maxAttempts = (int) config('gintly.auth.max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return;
        }

        $retryAfter = RateLimiter::availableIn($throttleKey);

        // límite de intentos -> 429 con Retry-After.
        throw new ThrottleRequestsException(
            'Demasiados intentos de acceso. Reintente en '.$retryAfter.' segundos.',
            null,
            ['Retry-After' => (string) $retryAfter],
        );
    }

    private function throttleKey(string $businessSlug, string $email, string $ip): string
    {
        return Str::lower($businessSlug.'|'.$email.'|'.$ip);
    }

    private function decoyHash(): string
    {
        // Hash bcrypt real y efímero; su único fin es igualar el costo temporal en la rama sin usuario.
        return $this->decoyHash ??= Hash::make(Str::random(40));
    }
}
