<?php

declare(strict_types=1);

use App\Http\Middleware\SetPermissionsTeamId;
use App\Exceptions\CustomerHasReceivablesException;
use App\Exceptions\InvalidPurchaseStateException;
use App\Exceptions\ProtectedResourceException;
use App\Exceptions\PurchaseMatchException;
use App\Exceptions\SupplierNotApprovedException;
use App\Http\Resources\GoodsReceiptResource;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;



return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // SetPermissionsTeamId se aplica globalmente en la API para asegurar la gestión
        // correcta del tenant, incluso en rutas públicas.
        $middleware->api(append: [
            SetPermissionsTeamId::class,
        ]);

        $middleware->throttleApi();

        $middleware->alias([
            'tenant.permissions' => SetPermissionsTeamId::class,
            'role'               => RoleMiddleware::class,
            'permission'         => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (SupplierNotApprovedException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'SUPPLIER_NOT_APPROVED',
            ], 422);
        });

        $exceptions->render(function (PurchaseMatchException $e, Request $request) {
            // D-10 · 409 CON el recurso creado. La evidencia persistió; el cliente
            // recibe el goods_receipt completo para que ROL-01 lo resuelva.
            return response()->json([
                'message'       => $e->getMessage(),
                'code'          => 'PURCHASE_MATCH',
                'goods_receipt' => (new GoodsReceiptResource($e->receipt->load(['items', 'accountPayable'])))->toArray($request),
            ], 409);
        });

        $exceptions->render(function (InvalidPurchaseStateException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        $exceptions->render(function (ProtectedResourceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'PROTECTED_RESOURCE',
            ], 403);
        });

        $exceptions->render(function (CustomerHasReceivablesException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'CUSTOMER_HAS_RECEIVABLES',
            ], 422);
        });
        
    })->create();
