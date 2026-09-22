<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Http\Requests\User\IndexUserRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UserService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionInvalidator $sessions,
    ) {}

    /**
     * seguridad: User NO tiene BusinessScope global. El filtro por business_id
     * es obligatorio y manual; sin él, index fugaría usuarios de otros negocios.
     *
     * Aplica los filtros del contrato MOD-01 (is_active, branch_id, search,
     * rango de fechas, trashed) y el ordenamiento/paginación validados por
     * IndexUserRequest. El allowlist de `sort` y el tope de `per_page` viven en
     * el FormRequest; aquí solo se consumen sus helpers ya saneados.
     */
    public function paginate(IndexUserRequest $request): LengthAwarePaginator
    {
        $query = User::query()
            ->with('roles')
            ->where('business_id', $this->actor()->business_id);

        // Borrado lógico: por defecto solo activos. `with` incluye dados de baja;
        // `only` los aísla. El candado parcial email_lock permite reutilizar correos.
        $trashed = $request->validated('trashed');
        if ($trashed === 'with') {
            $query->withTrashed();
        } elseif ($trashed === 'only') {
            $query->onlyTrashed();
        }

        $query
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active'))
            )
            ->when(
                $request->validated('branch_id'),
                fn ($q, $branchId) => $q->where('branch_id', $branchId)
            )
            ->when(
                $request->validated('search'),
                fn ($q, $search) => $q->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
            )
            // El rango se interpreta en el huso del negocio y llega en UTC.
            ->when(
                $request->fromDateTime(),
                fn ($q, $from) => $q->where('created_at', '>=', $from)
            )
            ->when(
                $request->toDateTime(),
                fn ($q, $to) => $q->where('created_at', '<=', $to)
            );

        return $query
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());
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
        DB::transaction(function () use ($user, $newPassword): void {
            $user->password = $newPassword;
            $user->save();

            // RF-01-03: se registra el hecho, NUNCA el valor de la contraseña.
            $this->audit->record('password_reset', $user);

            // El usuario destino pierde TODAS sus sesiones: un reseteo administrativo
            // invalida cualquier acceso previo con la credencial anterior.
            $this->sessions->flushAllForUser($user);
        });
    }

    /**
     * Autoservicio /me/password. La clave actual ya fue verificada por
     * current_password:web en el FormRequest.
     *
     * @param  string|null  $keepSessionId  Sesión actual a conservar; las demás se cierran.
     */
    public function updateOwnPassword(User $user, string $newPassword, ?string $keepSessionId = null): void
    {
        DB::transaction(function () use ($user, $newPassword, $keepSessionId): void {
            $user->password = $newPassword;
            $user->save();

            // Autoservicio: actor y afectado son el mismo usuario. Sin valores.
            $this->audit->record('password_reset', $user, actor: $user);

            // Se conserva la sesión actual del titular y se cierran las demás.
            $this->sessions->flushOtherSessionsForUser($user, $keepSessionId);
        });
    }

    /** Cambio de correo administrativo. Unicidad por-negocio validada en el FormRequest. */
    public function changeEmail(User $user, string $newEmail): void
    {
        DB::transaction(function () use ($user, $newEmail): void {
            $oldEmail = $user->email;

            $user->email = $newEmail;
            $user->save();

            // RF-01-03: cambio de identidad con valor anterior/nuevo.
            $this->audit->record(
                'update',
                $user,
                old: ['email' => $oldEmail],
                new: ['email' => $newEmail],
            );

            // El identificador de acceso cambió: se cierran todas las sesiones
            // del usuario destino para forzar reautenticación con el nuevo correo.
            $this->sessions->flushAllForUser($user);
        });
    }

    /** Reemplaza el rol, no acumula. Team ya fijado por SetPermissionsTeamId. */
    public function changeRole(User $user, string $role): User
    {
        return DB::transaction(function () use ($user, $role): User {
            $oldRole = $user->getRoleNames()->first();

            $user->syncRoles([$role]);

            // RF-01-03: segregación de funciones. Se conserva el rol previo y el nuevo.
            $this->audit->record(
                'role_changed',
                $user,
                old: ['role' => $oldRole],
                new: ['role' => $role],
            );

            return $user->load('roles');
        });
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard(config('gintly.tenant.api_guard'))->user()
            ?? throw new AuthenticationException();

        return $user;
    }
}
