<?php

declare(strict_types=1);

use App\Http\Middleware\SetPermissionsTeamId;
use App\Exceptions\CashAuthorizationException;
use App\Exceptions\CustomerHasReceivablesException;
use App\Exceptions\CyclicReferenceException;
use App\Exceptions\ImmutableInvoiceException;
use App\Exceptions\InvalidPurchaseStateException;
use App\Exceptions\IncompletePaymentException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidInvoiceStateException;
use App\Exceptions\ProtectedResourceException;
use App\Exceptions\RestrictDeleteException;
use App\Exceptions\SupplierNotApprovedException;
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

        // Activa el contexto del tenant para las peticiones Web (Vistas de Blade / Sidebar)
        $middleware->web(append: [
            SetPermissionsTeamId::class,
        ]);
        
        // Activa el contexto del tenant para la API (Peticiones AJAX)
        $middleware->api(append: [
            SetPermissionsTeamId::class,
        ]);

        $middleware->throttleApi();
        // Activa el soporte de sesiones/cookies para Sanctum SPA requerido por el AuthController
        $middleware->statefulApi();

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

        // PurchaseMatchException NO se mapea aquí: define su propio render() (que
        // Laravel prioriza) devolviendo GoodsReceiptResource (envuelto en `data`)
        // + message a 409. Un render duplicado aquí sería código muerto.

        $exceptions->render(function (InvalidPurchaseStateException $e, Request $request) {
            // 409 para estados inválidos; 422 para sobrepago de CxP (contrato MOD-04).
            return response()->json(['message' => $e->getMessage()], $e->status);
        });

        $exceptions->render(function (ProtectedResourceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'PROTECTED_RESOURCE',
            ], 403);
        });

        // ERR-02B · 409. Maestro con dependencias vigentes: no admite baja.
        // Excepción compartida (sucursales en MOD-01; unidades/categorías en MOD-02)
        // que carecía de render y por tanto degradaba a 500.
        $exceptions->render(function (RestrictDeleteException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'ERR-02B',
            ], 409);
        });

        // ERR-02 · 422. Ciclo en jerarquía de categorías o composición de recetas
        // (MOD-02). Sin render propio degradaba a 500 en lugar del 422 del contrato.
        $exceptions->render(function (CyclicReferenceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'ERR-02',
            ], 422);
        });

        $exceptions->render(function (CustomerHasReceivablesException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'CUSTOMER_HAS_RECEIVABLES',
            ], 422);
        });

        // NoActiveCashSessionException, CashSessionConflictException y
        // UnreconciledCashClosingException definen su propio render() (que Laravel
        // prioriza); un closure aquí sería código muerto —igual que se documenta para
        // PurchaseMatchException arriba— y además divergía en forma (clave/`code`).
        // Solo CashAuthorizationException carece de render propio y se mapea aquí.
        $exceptions->render(function (CashAuthorizationException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (ImmutableInvoiceException $e, Request $request) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'IMMUTABLE_INVOICE'], 403);
        });

        $exceptions->render(function (IncompletePaymentException $e, Request $request) {
            // Rollback estricto: NADA se persistió. Solo la señal 422.
            return response()->json(['message' => $e->getMessage(), 'code' => 'INCOMPLETE_PAYMENT'], 422);
        });

        $exceptions->render(function (InvalidInvoiceStateException $e, Request $request) {
            // Folio en conflicto es 409; el resto de estados inválidos, 422.
            $status = str_contains($e->getMessage(), 'folio') ? 409 : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        });

        // InsufficientStockException ya está mapeada en MOD-03 a 409 (INSUFFICIENT_STOCK).
        // Al reservar, su lanzamiento revierte toda la facturación.   
      
    })->create();
