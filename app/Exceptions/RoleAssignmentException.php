<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Escalada de privilegios bloqueada (defensa en profundidad, RF-01).
 * Un actor intentó conceder un rol de autoridad superior a la suya o el rol de sistema
 * (ROL-SYS), que jamás es asignable a un usuario humano. Se traduce a un 403 controlado.
 */
final class RoleAssignmentException extends RuntimeException
{
    public function __construct(string $message = 'No puede asignar ese rol.')
    {
        parent::__construct($message);
    }

    public static function systemRole(): self
    {
        return new self('El rol de sistema (ROL-SYS) no es asignable a un usuario humano.');
    }

    public static function aboveActor(): self
    {
        return new self('No puede asignar un rol de autoridad superior a la suya.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'ROLE_ASSIGNMENT_FORBIDDEN',
        ], 403);
    }
}
