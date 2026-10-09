<?php

declare(strict_types=1);

use App\Exceptions\CashAuthorizationException;
use App\Exceptions\CustomerHasReceivablesException;
use App\Exceptions\CyclicReferenceException;
use App\Exceptions\ImmutableInvoiceException;
use App\Exceptions\IncompletePaymentException;
use App\Exceptions\InvalidInvoiceStateException;
use App\Exceptions\InvalidPurchaseStateException;
use App\Exceptions\ProtectedResourceException;
use App\Exceptions\RestrictDeleteException;
use App\Exceptions\SupplierNotApprovedException;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\RequiresPlanFeature;
use App\Http\Middleware\SetPermissionsTeamId;
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
        // Forzar a Laravel a confiar en los balanceadores de carga de Azure
        $middleware->trustProxies(at: '*');

        // Activa el contexto del tenant para las peticiones Web
        $middleware->web(append: [
            SetPermissionsTeamId::class,
        ]);
        
        // Activa el contexto del tenant para la API
        $middleware->api(append: [
            SetPermissionsTeamId::class,
        ]);

        $middleware->throttleApi();
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/v1/billing/webhook',
        ]);

        $middleware->alias([
            'tenant.permissions' => SetPermissionsTeamId::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'plan.feature' => RequiresPlanFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (SupplierNotApprovedException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'SUPPLIER_NOT_APPROVED',
            ], 422);
        });

        $exceptions->render(function (InvalidPurchaseStateException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->status);
        });

        $exceptions->render(function (ProtectedResourceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'PROTECTED_RESOURCE',
            ], 403);
        });

        $exceptions->render(function (RestrictDeleteException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'ERR-02B',
            ], 409);
        });

        $exceptions->render(function (CyclicReferenceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'ERR-02',
            ], 422);
        });

        $exceptions->render(function (CustomerHasReceivablesException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'CUSTOMER_HAS_RECEIVABLES',
            ], 422);
        });

        $exceptions->render(function (CashAuthorizationException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        });

        $exceptions->render(function (ImmutableInvoiceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'IMMUTABLE_INVOICE',
            ], 403);
        });

        $exceptions->render(function (IncompletePaymentException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INCOMPLETE_PAYMENT',
            ], 422);
        });

        $exceptions->render(function (InvalidInvoiceStateException $e, Request $request) {
            $status = str_contains($e->getMessage(), 'folio') ? 409 : 422;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        });
    })->create();