<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountPayableController;
use App\Http\Controllers\Api\V1\AccountReceivableController;
use App\Http\Controllers\Api\V1\AnomalyController;
use App\Http\Controllers\Api\V1\AnomalyRuleController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\BusinessGoalController;
use App\Http\Controllers\Api\V1\CashMovementController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerCreditController; // Reutilizado de MOD-08.
use App\Http\Controllers\Api\V1\DispatchController;
use App\Http\Controllers\Api\V1\GoodsReceiptController;
use App\Http\Controllers\Api\V1\InventoryAdjustmentController;
use App\Http\Controllers\Api\V1\InventoryMovementController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\InvoiceDeliveryController;
use App\Http\Controllers\Api\V1\KpiSnapshotController;
use App\Http\Controllers\Api\V1\PhysicalCountController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductRecipeController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\ReconciliationRunController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportDefinitionController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SalesReturnController;
use App\Http\Controllers\Api\V1\StockLevelController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\StockLevel;
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

        //MOD-04 - Proveedores, Compras y Recepcion
        Route::apiResource('suppliers', SupplierController::class)
            ->parameters(['suppliers' => 'supplier']);
        Route::post('suppliers/{supplier}/approve', [SupplierController::class, 'approve']);
        Route::post('suppliers/{supplier}/suspend', [SupplierController::class, 'suspend']);

        // Órdenes de compra — CRUD parcial + transiciones.
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show']);
        Route::put('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update']);
        Route::post('purchase-orders/{purchase_order}/issue', [PurchaseOrderController::class, 'issue']);
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);

        // Recepciones — registrar (3-Way Match), ver, evidencia, resolver (ROL-01).
        Route::get('goods-receipts', [GoodsReceiptController::class, 'index']);
        Route::post('goods-receipts', [GoodsReceiptController::class, 'store']);
        Route::get('goods-receipts/{goods_receipt}', [GoodsReceiptController::class, 'show']);
        Route::get('goods-receipts/{goods_receipt}/items', [GoodsReceiptController::class, 'items']);
        Route::post('goods-receipts/{goods_receipt}/resolve', [GoodsReceiptController::class, 'resolve']);

        // Cuentas por pagar — listar, ver, pagar, descongelar (ROL-01).
        Route::get('accounts-payable', [AccountPayableController::class, 'index']);
        Route::get('accounts-payable/{account_payable}', [AccountPayableController::class, 'show']);
        Route::post('accounts-payable/{account_payable}/pay', [AccountPayableController::class, 'pay']);
        Route::post('accounts-payable/{account_payable}/unblock', [AccountPayableController::class, 'unblock']);

        // Binding de modelos para parámetros no convencionales.
        Route::model('purchase_order', \App\Models\PurchaseOrder::class);
        Route::model('goods_receipt', \App\Models\GoodsReceipt::class);
        Route::model('account_payable', \App\Models\AccountPayable::class);

        // MOD-05 - Clientes
        Route::apiResource('customers', CustomerController::class)
            ->parameters(['customers' => 'customer']);

        // Direcciones — sub-recurso del cliente con scopeBindings (doble binding H-47).
        Route::prefix('customers/{customer}/addresses')
            ->scopeBindings()
            ->group(function (): void {
                Route::get('/', [CustomerAddressController::class, 'index']);
                Route::post('/', [CustomerAddressController::class, 'store']);
                Route::put('/{address}', [CustomerAddressController::class, 'update']);
                Route::delete('/{address}', [CustomerAddressController::class, 'destroy']);
            });

        // Binding de modelos.
        Route::model('customer', \App\Models\Customer::class);
        Route::model('address', \App\Models\CustomerAddress::class);

        // MOD-06 - Gestion de Caja
        // Cajas — CRUD.
        Route::apiResource('cash-registers', CashRegisterController::class)
            ->parameters(['cash-registers' => 'cash_register']);

        // Sesiones — abrir, listar, ver, cerrar, movimientos.
        Route::get('cash-sessions', [CashSessionController::class, 'index']);
        Route::post('cash-sessions/open', [CashSessionController::class, 'open']);
        Route::get('cash-sessions/{cash_session}', [CashSessionController::class, 'show']);
        Route::post('cash-sessions/{cash_session}/close', [CashSessionController::class, 'close']);
        Route::get('cash-sessions/{cash_session}/movements', [CashSessionController::class, 'movements']);

        // Movimientos — listar (append-only), registrar manual.
        Route::get('cash-movements', [CashMovementController::class, 'index']);
        Route::post('cash-movements', [CashMovementController::class, 'store']);

        Route::model('cash_register', \App\Models\CashRegister::class);
        Route::model('cash_session', \App\Models\CashSession::class);        

        // MOD-07 - Ventas, Facturacion e Inmutabilidad

        Route::get('sales', [SaleController::class, 'index']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);
        Route::post('sales/{sale}/confirm', [SaleController::class, 'confirm']);

        // Ítems de venta — sub-recurso con scopeBindings (doble binding).
        Route::prefix('sales/{sale}/items')
            ->scopeBindings()
            ->group(function (): void {
                Route::post('/', [SaleController::class, 'addItem']);
                Route::delete('/{item}', [SaleController::class, 'removeItem']);
            });

        // Facturas — emitir, ver, pagos, anular. PUT devuelve 403 (inmutable).
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('invoices/{invoice}/payments', [InvoiceController::class, 'payments']);
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);   // → 403 IMMUTABLE_INVOICE
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);

        Route::model('sale', \App\Models\Sale::class);
        Route::model('item', \App\Models\SaleItem::class);
        Route::model('invoice', \App\Models\Invoice::class);

        // MOD-08 · Cuentas por Cobrar
        Route::prefix('accounts-receivable')->group(function (): void {
            Route::get('/', [AccountReceivableController::class, 'index'])
                ->name('accounts-receivable.index');

            Route::get('/{accountReceivable}', [AccountReceivableController::class, 'show'])
                ->name('accounts-receivable.show');

            Route::get('/{accountReceivable}/payments', [AccountReceivableController::class, 'payments'])
                ->name('accounts-receivable.payments.index');

            Route::post('/{accountReceivable}/payments', [AccountReceivableController::class, 'storePayment'])
                ->name('accounts-receivable.payments.store');
        });

        // MOD-09 Crédito del cliente (sub-recurso de customers)
        Route::prefix('customers/{customer}')->group(function (): void {
            Route::get('credit-status', [CustomerCreditController::class, 'status'])
                ->name('customers.credit-status');

            Route::post('credit-check', [CustomerCreditController::class, 'check'])
            ->name('customers.credit-check');
        });

        // MOD-09 · Entregas y Retiros
        Route::prefix('dispatches')->group(function (): void {
            Route::get('/', [DispatchController::class, 'index'])->name('dispatches.index');
            Route::post('/', [DispatchController::class, 'store'])->name('dispatches.store');
            Route::get('/{dispatch}', [DispatchController::class, 'show'])->name('dispatches.show');
            Route::get('/{dispatch}/items', [DispatchController::class, 'items'])->name('dispatches.items');
            Route::post('/{dispatch}/revert', [DispatchController::class, 'revert'])->name('dispatches.revert');
        });

        // MOD-09 · Saldo pendiente de entrega (sub-recurso de invoices)
        Route::get('invoices/{invoice}/delivery-status', [InvoiceDeliveryController::class, 'show'])
            ->name('invoices.delivery-status');


        // MOD-10 · Devoluciones
        Route::prefix('sales-returns')->group(function (): void {
            Route::get('/', [SalesReturnController::class, 'index'])->name('sales-returns.index');
            Route::post('/', [SalesReturnController::class, 'store'])->name('sales-returns.store');
            Route::get('/{salesReturn}', [SalesReturnController::class, 'show'])->name('sales-returns.show');
            Route::get('/{salesReturn}/items', [SalesReturnController::class, 'items'])->name('sales-returns.items');
        });

        // MOD-10 · Notas de crédito
        Route::prefix('credit-notes')->group(function (): void {
            Route::get('/', [CreditNoteController::class, 'index'])->name('credit-notes.index');
            Route::get('/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
        });

        // MOD-10 · Saldo a favor del cliente
        Route::get('customers/{customer}/credit-balance', [CustomerCreditController::class, 'creditBalance'])
            ->name('customers.credit-balance');

        // MOD-11 · Reglas de anomalía
        Route::prefix('anomaly-rules')->group(function (): void {
            Route::get('/', [AnomalyRuleController::class, 'index'])->name('anomaly-rules.index');
            Route::put('/{anomalyRule}', [AnomalyRuleController::class, 'update'])->name('anomaly-rules.update');
        });

        // MOD-11 · Anomalías (máquina de estados)
        Route::prefix('anomalies')->group(function (): void {
            Route::get('/', [AnomalyController::class, 'index'])->name('anomalies.index');
            Route::get('/{anomaly}', [AnomalyController::class, 'show'])->name('anomalies.show');
            Route::get('/{anomaly}/events', [AnomalyController::class, 'events'])->name('anomalies.events');
            Route::post('/{anomaly}/justify', [AnomalyController::class, 'justify'])->name('anomalies.justify');
            Route::post('/{anomaly}/resolve', [AnomalyController::class, 'resolve'])->name('anomalies.resolve');
        });

        // MOD-11 · Corridas de conciliación
        Route::prefix('reconciliation-runs')->group(function (): void {
            Route::get('/', [ReconciliationRunController::class, 'index'])->name('reconciliation-runs.index');
            Route::post('/', [ReconciliationRunController::class, 'store'])->name('reconciliation-runs.store');
            Route::get('/{reconciliationRun}', [ReconciliationRunController::class, 'show'])->name('reconciliation-runs.show');
        });

        // MOD-12 · Metas de negocio
        Route::prefix('business-goals')->group(function (): void {
            Route::get('/', [BusinessGoalController::class, 'index'])->name('business-goals.index');
            Route::post('/', [BusinessGoalController::class, 'store'])->name('business-goals.store');
            Route::put('/{businessGoal}', [BusinessGoalController::class, 'update'])->name('business-goals.update');
            Route::delete('/{businessGoal}', [BusinessGoalController::class, 'destroy'])->name('business-goals.destroy');
        });

        // MOD-12 · Instantáneas de KPI y panel
        Route::prefix('kpi-snapshots')->group(function (): void {
            Route::get('/', [KpiSnapshotController::class, 'index'])->name('kpi-snapshots.index');
            Route::post('/recalculate', [KpiSnapshotController::class, 'recalculate'])->name('kpi-snapshots.recalculate');
        });
        Route::get('dashboard/kpis', [KpiSnapshotController::class, 'dashboard'])->name('dashboard.kpis');

        // MOD-12 · Reportes
        Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
        Route::prefix('report-definitions')->group(function (): void {
            Route::get('/', [ReportDefinitionController::class, 'index'])->name('report-definitions.index');
            Route::post('/', [ReportDefinitionController::class, 'store'])->name('report-definitions.store');
            Route::put('/{reportDefinition}', [ReportDefinitionController::class, 'update'])->name('report-definitions.update');
            Route::delete('/{reportDefinition}', [ReportDefinitionController::class, 'destroy'])->name('report-definitions.destroy');
        });
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