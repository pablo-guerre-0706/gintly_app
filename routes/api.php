<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;


// 'v1' se antepone para componer /api/v1 conforme al contrato MOD-01 V2.
Route::prefix('v1')->group(function (): void {

    // Público. El límite de intentos es throttle, no contador en BD.
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me/password', [UserController::class, 'updateOwnPassword']);

        // apiResource genera los parámetros {user} y {branch}, que son los
        // que esperan routeId() en los FormRequest y el ignore() del unique.
        Route::apiResource('users', UserController::class);
        Route::put('/users/{user}/role', [UserController::class, 'updateRole']);
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword']);
        Route::put('/users/{user}/email', [UserController::class, 'updateEmail']);

        Route::apiResource('branches', BranchController::class);

        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::get('/business', [BusinessController::class, 'show']);
        Route::put('/business', [BusinessController::class, 'update']);
    });
});



/*
MOD-01 · POST   /api/v1/auth/login       · LoginRequest           · RF-01-02  · público
MOD-01 · POST   /api/v1/auth/logout      · —                      · RF-01-02  · autenticado
MOD-01 · GET    /api/v1/me               · —                      · RF-01-02  · autenticado

MOD-01 · GET    /api/v1/users            · IndexUserRequest ▲     · RF-01-01  · ROL-02, ROL-01
MOD-01 · POST   /api/v1/users            · StoreUserRequest       · RF-01-01  · ROL-02
MOD-01 · GET    /api/v1/users/{id}       · —                      · RF-01-01  · ROL-02
MOD-01 · PUT    /api/v1/users/{id}       · UpdateUserRequest      · RF-01-01  · ROL-02
MOD-01 · PUT    /api/v1/users/{id}/role  · UpdateUserRoleRequest  · RF-01-01  · ROL-02
MOD-01 · DELETE /api/v1/users/{id}       · —                      · RF-01-01  · ROL-02

MOD-01 · GET    /api/v1/branches         · IndexBranchRequest ▲   · RF-01-01† · ROL-02, ROL-01
MOD-01 · POST   /api/v1/branches         · StoreBranchRequest     · RF-01-01† · ROL-02
MOD-01 · GET    /api/v1/branches/{id}    · —                      · RF-01-01† · ROL-02
MOD-01 · PUT    /api/v1/branches/{id}    · UpdateBranchRequest    · RF-01-01† · ROL-02
MOD-01 · DELETE /api/v1/branches/{id}    · —                      · RF-01-01† · ROL-02

MOD-01 · GET    /api/v1/audit-logs       · IndexAuditLogRequest ▲ · RF-01-03  · ROL-01, ROL-02
MOD-01 · GET    /api/v1/business         · —                      · RF-01-05† · ROL-01
MOD-01 · PUT    /api/v1/business         · UpdateBusinessRequest  · RF-01-05† · ROL-01
*/