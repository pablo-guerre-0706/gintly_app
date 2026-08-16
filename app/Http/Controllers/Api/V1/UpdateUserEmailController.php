<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateUserEmailRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class UpdateUserEmailController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserService $users,
    ) {}

    public function __invoke(UpdateUserEmailRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $this->users->changeEmail($user, $request->validated('email'));

        return new UserResource($user->refresh());
    }
}