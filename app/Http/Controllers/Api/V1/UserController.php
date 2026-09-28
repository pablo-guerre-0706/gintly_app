<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AssignProfilesRequest;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\ProfileService;
use App\Services\Users\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserService $users,
    ) {
        // Centraliza la autorización de política: index->viewAny, show->view, store->create, update->update, destroy->delete.
        // show/update/destroy reciben el modelo -> UserPolicy::sharesBusinessWith() bloquea el cross-tenant en el binding.
        $this->authorizeResource(User::class, 'user');
    }

    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        // IndexUserRequest valida y sanea filtros, orden y paginación (contrato MOD-01).
        // viewAny lo cubre authorizeResource; el aislamiento por negocio, el servicio.
        return UserResource::collection(
            $this->users->paginate($request),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($user, $request->validated()));
    }

    public function destroy(User $user): Response
    {
        $this->users->deactivate($user);

        return response()->noContent();
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): UserResource
    {
        // La FK de tenant la valida el binding (UserPolicy::sharesBusinessWith); la anti-escalación vive en UserPolicy.
        $this->authorize('update', $user);

        return new UserResource($this->users->changeRole($user, $request->validated('role'), $request->validated()));
    }

    /** GET /users/{user}/profiles — perfiles operativos del usuario (ROL-03). */
    public function showProfiles(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('operativeProfiles', 'roles'));
    }

    /** PUT /users/{user}/profiles — reemplaza el conjunto de perfiles operativos (ROL-03). */
    public function updateProfiles(AssignProfilesRequest $request, User $user, ProfileService $profiles): UserResource
    {
        // Autorización (rango + objetivo ROL-03 del mismo negocio) en UserPolicy::manageProfiles vía el Request.
        $actor = $request->user();
        $updated = $profiles->replace($user, $request->validated('profiles'), $actor);

        return new UserResource($updated->load('roles'));
    }
}
