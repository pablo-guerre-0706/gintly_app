<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\ProfileService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Fase 3 · Catálogo de perfiles operativos disponibles (cajero/facturador/bodeguero/despachador).
 * Solo-lectura desde el registro canónico config/profiles.php. Visible para quien administra usuarios.
 */
final class OperativeProfileController extends Controller
{
    use AuthorizesRequests;

    public function index(ProfileService $profiles): JsonResponse
    {
        $this->authorize('viewAny', User::class); // ROL-02+ (administra usuarios).

        return response()->json(['data' => $profiles->catalog()]);
    }
}
