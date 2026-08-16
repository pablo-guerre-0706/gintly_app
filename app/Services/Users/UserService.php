<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UserService
{
    /**
     * seguridad: User NO tiene BusinessScope global. El filtro por business_id
     * es obligatorio y manual; sin él, index fugaría usuarios de otros negocios.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->where('business_id', $this->actor()->business_id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = new User();

            // fill respeta $fillable (name, email, password, is_active, branch_id).
            // El cast 'hashed' cifra password al guardar. NUNCA Hash::make aquí.
            $user->fill(Arr::except($data, ['business_id', 'role']));

            // business_id está fuera de $fillable -> asignación directa desde la sesión, saltando mass-assignment.
            $user->business_id = $this->actor()->business_id;

            $user->save();

            // exactamente un rol activo. Team ya fijado (business del actor = business del nuevo usuario).
            $user->syncRoles([$data['role']]);

            return $user->refresh()->load('roles');
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            // email y password se gestionan en endpoints dedicados; nunca por esta vía.
            $user->fill(Arr::only($data, ['name', 'is_active', 'branch_id']));
            $user->save();

            if (array_key_exists('role', $data)) {
                $user->syncRoles([$data['role']]);
            }

            return $user->refresh()->load('roles');
        });
    }

    /** borrado lógico. Conserva historial; el partial lock users.email_lock libera el correo. */
    public function deactivate(User $user): void
    {
        $user->delete();
    }

    /** Reset administrativo. texto plano -> cast 'hashed'. */
    public function resetPassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();
    }

    /** Autoservicio /me/password. La clave actual ya fue verificada por current_password:web en el FormRequest. */
    public function updateOwnPassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();
    }

    /** Cambio de correo administrativo. Unicidad por-negocio validada en el FormRequest. */
    public function changeEmail(User $user, string $newEmail): void
    {
        $user->email = $newEmail;
        $user->save();
    }

    /** Reemplaza el rol, no acumula. Team ya fijado por SetPermissionsTeamId. */
    public function changeRole(User $user, string $role): User
    {
        $user->syncRoles([$role]);

        return $user->load('roles');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard(config('gintly.tenant.api_guard'))->user()
            ?? throw new AuthenticationException();

        return $user;
    }
}
