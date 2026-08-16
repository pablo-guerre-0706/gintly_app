<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->users->paginate($request->integer('per_page', 15)),
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

        return new UserResource($this->users->changeRole($user, $request->validated('role')));
    }   
}
