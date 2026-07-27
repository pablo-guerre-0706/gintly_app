<?php

declare(strict_types=1);

use App\Http\Middleware\SetPermissionsTeamId;
use App\Exceptions\CashAuthorizationException;
use App\Exceptions\CashSessionConflictException;
use App\Exceptions\CustomerHasReceivablesException;
use App\Exceptions\ImmutableInvoiceException;
use App\Exceptions\InvalidPurchaseStateException;
use App\Exceptions\IncompletePaymentException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidInvoiceStateException;
use App\Exceptions\NoActiveCashSessionException;
use App\Exceptions\ProtectedResourceException;
use App\Exceptions\PurchaseMatchException;
use App\Exceptions\SupplierNotApprovedException;
use App\Exceptions\UnreconciledCashClosingException;
use App\Http\Resources\CashSessionResource;
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

        $exceptions->render(function (NoActiveCashSessionException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'NO_ACTIVE_CASH_SESSION',
            ], 409);
        });

        $exceptions->render(function (CashSessionConflictException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        $exceptions->render(function (CashAuthorizationException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (UnreconciledCashClosingException $e, Request $request) {
            // D-20 · 422 CON la sesión persistida. Ya está cerrada (descuadrada), así
            // que el Resource revela expected_amount y difference: es la evidencia.
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'UNRECONCILED_CASH_CLOSING',
                'cash_session' => (new CashSessionResource($e->session))->toArray($request),
            ], 422);
        });

        $exceptions->render(function (ImmutableInvoiceException $e, Request $request) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'IMMUTABLE_INVOICE'], 403);
        });

        $exceptions->render(function (IncompletePaymentException $e, Request $request) {
            // D-27 · Rollback estricto: NADA se persistió. Solo la señal 422.
            return response()->json(['message' => $e->getMessage(), 'code' => 'INCOMPLETE_PAYMENT'], 422);
        });

        $exceptions->render(function (InvalidInvoiceStateException $e, Request $request) {
            // Folio en conflicto es 409; el resto de estados inválidos, 422.
            $status = str_contains($e->getMessage(), 'folio') ? 409 : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        });

        // InsufficientStockException ya está mapeada en MOD-03 a 409 (INSUFFICIENT_STOCK).
        // Al reservar, su lanzamiento revierte toda la facturación (D-27).   
      
    })->create();
