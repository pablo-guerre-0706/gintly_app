<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateOwnPasswordRequest;
use App\Services\Users\UserService;
use Illuminate\Http\Response;

final class UpdatePasswordController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function __invoke(UpdateOwnPasswordRequest $request): Response
    {
        // Identidad ya probada por la regla current_password:web del FormRequest -> no requiere authorize adicional.
        // Se conserva la sesión actual del titular; las demás se invalidan en el servicio.
        $keepSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $this->users->updateOwnPassword($request->user(), $request->validated('password'), $keepSessionId);

        return response()->noContent();
    }
}
