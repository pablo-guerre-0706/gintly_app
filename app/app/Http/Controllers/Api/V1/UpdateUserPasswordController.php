<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResetUserPasswordRequest;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;

final class UpdateUserPasswordController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserService $users,
    ) {}

    public function __invoke(ResetUserPasswordRequest $request, User $user): Response
    {
        $this->authorize('update', $user); // UserPolicy: sharesBusinessWith + anti-escalación

        $this->users->resetPassword($user, $request->validated('password'));

        return response()->noContent();
    }
}
