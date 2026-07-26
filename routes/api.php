<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\InventoryAdjustmentController;
use App\Http\Controllers\Api\V1\InventoryMovementController;
use App\Http\Controllers\Api\V1\PhysicalCountController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductRecipeController;
use App\Http\Controllers\Api\V1\StockLevelController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Models\Product;
use App\Models\ProductRecipe;
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


        // MOD-02 · Catálogo y Datos Maestros //
        // Los parámetros los genera apiResource, coinciden con los routeId() de los FormRequest.

        Route::model('compound', Product::class);
        Route::model('line', ProductRecipe::class);
        
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('brands', BrandController::class);     

        // units: parámetro {unit} explícito para casar con route('unit').
        Route::apiResource('units', UnitController::class)->parameters(['units' => 'unit']);

        Route::apiResource('products', ProductController::class);

        // Receta como sub-recurso del compuesto. scopeBindings() obliga a que {line}
        Route::prefix('products/{compound}/recipe')
            ->scopeBindings()
            ->group(function (): void {
                Route::get('/', [ProductRecipeController::class, 'index']);
                Route::post('/', [ProductRecipeController::class, 'store']);
                Route::put('/{line}', [ProductRecipeController::class, 'update']);
                Route::delete('/{line}', [ProductRecipeController::class, 'destroy']);
            });
        
        // MOD-03 - Inventario logico y Bodega fisica
        Route::apiResource('warehouses', WarehouseController::class)
            ->parameters(['warehouses' => 'warehouse']);

        // Stock. Solo lectura + edición de umbrales. Sin POST.
        Route::get('stock', [StockLevelController::class, 'index']);
        Route::get('stock/{product}/{warehouse}', [StockLevelController::class, 'show']);
        Route::put('stock/{product}/{warehouse}/thresholds', [StockLevelController::class, 'updateThresholds']);

        // Conteos físicos: registrar, ver, aplicar (ajustar), justificar.
        Route::get('physical-counts', [PhysicalCountController::class, 'index']);
        Route::post('physical-counts', [PhysicalCountController::class, 'store']);
        Route::get('physical-counts/{physical_count}', [PhysicalCountController::class, 'show']);
        Route::post('physical-counts/{physical_count}/apply', [PhysicalCountController::class, 'apply']);
        Route::post('physical-counts/{physical_count}/justify', [PhysicalCountController::class, 'justify']);

        // Traspasos: crear, ver, completar, cancelar.
        Route::get('stock-transfers', [StockTransferController::class, 'index']);
        Route::post('stock-transfers', [StockTransferController::class, 'store']);
        Route::get('stock-transfers/{stock_transfer}', [StockTransferController::class, 'show']);
        Route::post('stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete']);
        Route::post('stock-transfers/{stock_transfer}/cancel', [StockTransferController::class, 'cancel']);

        // Ajustes directos: crear, listar, ver.
        Route::get('inventory-adjustments', [InventoryAdjustmentController::class, 'index']);
        Route::post('inventory-adjustments', [InventoryAdjustmentController::class, 'store']);
        Route::get('inventory-adjustments/{inventory_adjustment}', [InventoryAdjustmentController::class, 'show']);

        // Kardex, solo lectura (H-30).
        Route::get('inventory-movements', [InventoryMovementController::class, 'index']);

        // Binding de modelos para parámetros no convencionales.
        Route::model('physical_count', \App\Models\PhysicalCount::class);
        Route::model('stock_transfer', \App\Models\StockTransfer::class);
        Route::model('inventory_adjustment', \App\Models\InventoryAdjustment::class);

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