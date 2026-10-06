<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\RegistrationResultResource;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alta pública canónica: POST /api/v1/auth/register. Controlador delgado e invocable. La autorización
 * (visitante no autenticado) y la validación/normalización viven en RegisterRequest; la atomicidad y la
 * idempotencia en RegistrationService. NO autentica al propietario ni emite tokens.
 */
final class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registration,
    ) {
    }

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $result = $this->registration->register(
            $request->registrationData(),
            $request->idempotencyKey(),
        );

        // 201 tanto en el alta inicial como en la repetición idempotente exitosa.
        return RegistrationResultResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
