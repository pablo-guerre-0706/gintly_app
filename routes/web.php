<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\RegisterWizardController;
use App\Http\Middleware\EnsureOperableUser;
use Illuminate\Support\Facades\Route;

// ==========================================
// RUTAS PÚBLICAS Y LANDING PAGE
// ==========================================
Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/landing', function () {
    return view('landing');
});

// Inicio de sesión (Vista)
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// ==========================================
// ASISTENTE DE REGISTRO MULTI-PASO (1-7)
// ==========================================
Route::prefix('register')->name('register.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('register.step', ['step' => 1]);
    })->name('index');

    Route::get('/step/{step}', [RegisterWizardController::class, 'showStep'])
        ->where('step', '[1-7]')
        ->name('step');

    Route::get('/step/{step}/store', [RegisterWizardController::class, 'storeStep'])
        ->where('step', '[1-7]')
        ->name('step.store');
});

// ==========================================
// PANEL DE ADMINISTRACIÓN (DASHBOARD)
// ==========================================
Route::middleware(['auth', EnsureOperableUser::class])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::view('/pos', 'pos.index')->name('pos.index');

    Route::redirect('/finance/cash-closing', '/operations/cash/close')->name('finance.cash-closing');

    // Nombre 'web.customers.index' para NO colisionar con el recurso API 'customers.index'
    // (apiResource en routes/api.php). La colisión de nombres rompía route:cache. La URL /customers
    // y su vista se conservan intactas. La creación es su propia vista de panel (web.customers.create);
    // las vistas Blade nunca enlazan a nombres de rutas API de escritura.
    Route::view('/customers', 'customers.index')->name('web.customers.index');
    Route::view('/customers/create', 'customers.create')->name('web.customers.create');

    // Vistas directivas ROL-01. Estas rutas solo entregan estructura Blade;
    // los datos y la autorización de recurso permanecen en /api/v1.
    Route::view('/sales/summary', 'reports.summary', [
        'reportType' => 'ventas',
        'reportTitle' => 'Resumen e historial de ventas',
        'reportDescription' => 'Consulta la facturación consolidada por período y sucursal.',
        'breadcrumbRoot' => 'Ventas y clientes',
    ])->name('panel.sales.summary');

    Route::view('/inventory/summary', 'reports.summary', [
        'reportType' => 'inventario',
        'reportTitle' => 'Resumen consolidado de inventario',
        'reportDescription' => 'Vista consolidada del negocio para supervisar exactitud, desviaciones y faltantes detectados.',
        'breadcrumbRoot' => 'Inventario y bodega',
    ])->name('panel.inventory.summary');

    Route::view('/finance/cash-overview', 'reports.summary', [
        'reportType' => 'caja',
        'reportTitle' => 'Estado consolidado de cajas',
        'reportDescription' => 'Revisa cierres y diferencias agregadas sin ejecutar operaciones de caja.',
        'breadcrumbRoot' => 'Finanzas y créditos',
    ])->name('panel.finance.cash-overview');

    Route::view('/finance/receivables', 'reports.summary', [
        'reportType' => 'cartera',
        'reportTitle' => 'Cuentas por cobrar',
        'reportDescription' => 'Analiza la exposición, recuperación y cartera vencida del negocio.',
        'breadcrumbRoot' => 'Finanzas y créditos',
    ])->name('panel.finance.receivables');

    Route::view('/finance/payables', 'supervision.resource-list', [
        'resourceType' => 'payables',
        'pageTitle' => 'Cuentas por pagar',
        'pageDescription' => 'Consulta obligaciones vigentes con proveedores registrados.',
        'breadcrumbRoot' => 'Finanzas y créditos',
    ])->name('panel.finance.payables');

    Route::view('/inventory/reconciliation', 'inventory.reconciliation')->name('inventory.reconciliation');

    Route::view('/catalog/products', 'catalog.products')->name('catalog.products');

    // Hubs de navegación del panel. Son rutas exclusivamente presentacionales:
    // los datos y la autorización funcional se resuelven mediante /api/v1.
    Route::view('/purchases', 'hubs.purchases')->name('panel.purchases');
    Route::view('/finance', 'hubs.finance')->name('panel.finance');
    Route::view('/intelligence', 'hubs.intelligence')->name('panel.intelligence');
    Route::view('/organization', 'hubs.organization')->name('panel.organization');
    Route::view('/settings', 'hubs.settings')->name('panel.settings');
    Route::view('/help', 'hubs.help')->name('panel.help');

    Route::view('/suppliers', 'suppliers.index')->name('panel.suppliers.index');
    Route::view('/suppliers/explore', 'suppliers.explore')->name('panel.suppliers.explore');

    Route::view('/intelligence/anomalies', 'administration.anomalies')->name('panel.anomalies.index');
    Route::view('/intelligence/reconciliations', 'administration.reconciliations')->name('panel.reconciliations.index');
    Route::view('/intelligence/kpi-snapshots', 'supervision.resource-list', [
        'resourceType' => 'kpiSnapshots',
        'pageTitle' => 'Instantáneas KPI',
        'pageDescription' => 'Consulta los indicadores calculados que el Backend autoriza para administración.',
        'breadcrumbRoot' => 'Inteligencia y control',
    ])->name('panel.kpi-snapshots.index');
    Route::view('/intelligence/report-definitions', 'supervision.resource-list', [
        'resourceType' => 'reportDefinitions',
        'pageTitle' => 'Definiciones de reportes',
        'pageDescription' => 'Consulta las definiciones reutilizables configuradas para el negocio.',
        'breadcrumbRoot' => 'Inteligencia y control',
    ])->name('panel.report-definitions.index');
    Route::view('/intelligence/audit', 'supervision.resource-list', [
        'resourceType' => 'audit',
        'pageTitle' => 'Auditoría',
        'pageDescription' => 'Revisa la bitácora inmutable de acciones del negocio.',
        'breadcrumbRoot' => 'Inteligencia y control',
    ])->name('panel.audit.index');

    Route::view('/organization/users', 'organization.users.index')->name('panel.users.index');
    Route::view('/organization/users/create', 'organization.users.create')->name('panel.users.create');
    Route::view('/organization/users/{user}/access', 'organization.users.access')
        ->whereNumber('user')
        ->name('panel.users.access');
    Route::view('/organization/profiles', 'organization.profiles.index')->name('panel.profiles.index');
    Route::view('/organization/branches', 'organization.branches.index')->name('panel.branches.index');
    Route::view('/organization/branches/create', 'organization.branches.form', ['mode' => 'create'])
        ->name('panel.branches.create');
    Route::view('/organization/branches/{branch}/edit', 'organization.branches.form', ['mode' => 'edit'])
        ->whereNumber('branch')
        ->name('panel.branches.edit');

    // Supervisión administrativa ROL-02. Estas rutas no ejecutan lógica de
    // negocio; cada vista consume exclusivamente contratos /api/v1 existentes.
    Route::view('/administration/invoices', 'supervision.resource-list', [
        'resourceType' => 'invoices',
        'pageTitle' => 'Supervisión de facturas',
        'pageDescription' => 'Consulta facturas emitidas sin habilitar creación, cobro ni anulación.',
        'breadcrumbRoot' => 'Ventas y clientes',
    ])->name('panel.admin.invoices');
    Route::view('/administration/warehouses', 'administration.warehouses')->name('panel.admin.warehouses');
    Route::view('/administration/physical-counts', 'supervision.resource-list', [
        'resourceType' => 'physicalCounts',
        'pageTitle' => 'Conteos físicos',
        'pageDescription' => 'Consulta conteos pendientes y diferencias registradas para validación administrativa.',
        'breadcrumbRoot' => 'Catálogo e inventario',
    ])->name('panel.admin.physical-counts');
    Route::view('/administration/purchase-orders', 'supervision.resource-list', [
        'resourceType' => 'purchaseOrders',
        'pageTitle' => 'Órdenes de compra',
        'pageDescription' => 'Supervisa órdenes de compra y su estado contractual.',
        'breadcrumbRoot' => 'Compras y proveedores',
    ])->name('panel.admin.purchase-orders');
    Route::view('/administration/goods-receipts', 'supervision.resource-list', [
        'resourceType' => 'goodsReceipts',
        'pageTitle' => 'Recepciones',
        'pageDescription' => 'Consulta recepciones y discrepancias sin ejecutar recepción física ni resolución reservada.',
        'breadcrumbRoot' => 'Compras y proveedores',
    ])->name('panel.admin.goods-receipts');
    Route::view('/administration/cash-registers', 'administration.cash-registers')->name('panel.admin.cash-registers');
    Route::view('/administration/cash-sessions', 'administration.cash-sessions')->name('panel.admin.cash-sessions');
    Route::view('/administration/exchange-rates', 'administration.exchange-rates')->name('panel.admin.exchange-rates');

    // Experiencia operativa ROL-03. Estas rutas solo entregan vistas; perfiles,
    // capacidades, sucursal y autorización definitiva se resuelven en /api/v1.
    Route::view('/operations/cash', 'operations.cash')->name('panel.operations.cash');
    Route::view('/operations/cash/open', 'operations.cash-open')->name('panel.operations.cash.open');
    Route::view('/operations/cash/movements', 'operations.cash-movements')->name('panel.operations.cash.movements');
    Route::view('/operations/cash/count', 'operations.cash-count')->name('panel.operations.cash.count');
    Route::view('/operations/cash/close', 'operations.cash-close')->name('panel.operations.cash.close');
    Route::view('/operations/cash/history', 'operations.cash-history')->name('panel.operations.cash.history');
    Route::view('/operations/receivables', 'operations.receivables')->name('panel.operations.receivables');
    Route::view('/inventory/stock', 'inventory.stock', ['physical' => false])->name('panel.inventory.stock');
    Route::view('/operations/stock', 'inventory.stock', ['physical' => true])->name('panel.operations.stock');
    Route::view('/operations/physical-counts/new', 'operations.physical-count')->name('panel.operations.physical-count');
    Route::view('/operations/dispatches', 'operations.dispatches')->name('panel.operations.dispatches');
    Route::view('/operations/invoices', 'operations.resource-list', [
        'resourceType' => 'invoices', 'pageTitle' => 'Facturas de mi sucursal',
        'pageDescription' => 'Consulta documentos emitidos dentro de tu alcance operativo.',
        'breadcrumbRoot' => 'Consultas compartidas',
    ])->name('panel.operations.invoices');
    Route::view('/operations/sales', 'operations.resource-list', [
        'resourceType' => 'sales', 'pageTitle' => 'Ventas de mi sucursal',
        'pageDescription' => 'Consulta el estado de ventas de tu sucursal; las operaciones de facturación se realizan en el punto de venta.',
        'breadcrumbRoot' => 'Ventas y facturación',
    ])->name('panel.operations.sales');
    Route::redirect('/operations/warehouses', '/operations/stock')->name('panel.operations.warehouses');
    Route::view('/operations/stock-transfers', 'operations.resource-list', [
        'resourceType' => 'transfers', 'pageTitle' => 'Traspasos de inventario',
        'pageDescription' => 'Consulta traspasos cuyo origen o destino pertenece a tu sucursal.',
        'breadcrumbRoot' => 'Inventario y bodega',
    ])->name('panel.operations.transfers');
    Route::view('/operations/stock-transfers/new', 'operations.stock-transfer-create')->name('panel.operations.transfers.create');
    Route::view('/operations/purchase-orders', 'operations.resource-list', [
        'resourceType' => 'purchaseOrders', 'pageTitle' => 'Órdenes de compra',
        'pageDescription' => 'Consulta órdenes de compra de tu sucursal.',
        'breadcrumbRoot' => 'Compras y recepción',
    ])->name('panel.operations.purchase-orders');
    Route::view('/operations/purchase-orders/new', 'operations.purchase-order-create')->name('panel.operations.purchase-orders.create');
    Route::view('/operations/goods-receipts', 'operations.resource-list', [
        'resourceType' => 'goodsReceipts', 'pageTitle' => 'Recepciones de mercancía',
        'pageDescription' => 'Consulta recepciones realizadas en bodegas de tu sucursal.',
        'breadcrumbRoot' => 'Compras y recepción',
    ])->name('panel.operations.goods-receipts');
    Route::view('/operations/goods-receipts/new', 'operations.goods-receipt-create')->name('panel.operations.goods-receipts.create');
    Route::view('/operations/suppliers', 'operations.resource-list', [
        'resourceType' => 'suppliers', 'pageTitle' => 'Proveedores registrados',
        'pageDescription' => 'Consulta proveedores internos del negocio. Esta vista no aprueba ni suspende proveedores.',
        'breadcrumbRoot' => 'Compras y recepción',
    ])->name('panel.operations.suppliers');
    Route::view('/operations/accounts-payable', 'operations.resource-list', [
        'resourceType' => 'payables', 'pageTitle' => 'Cuentas por pagar',
        'pageDescription' => 'Consulta obligaciones vinculadas con órdenes de tu sucursal, sin realizar pagos ni desbloqueos.',
        'breadcrumbRoot' => 'Compras y recepción',
    ])->name('panel.operations.payables');
    Route::view('/operations/sales-returns', 'operations.resource-list', [
        'resourceType' => 'returns', 'pageTitle' => 'Devoluciones',
        'pageDescription' => 'Consulta devoluciones de facturas de tu sucursal.',
        'breadcrumbRoot' => 'Devoluciones',
    ])->name('panel.operations.returns');
    Route::view('/operations/sales-returns/new', 'operations.sales-return-create')->name('panel.operations.returns.create');
    Route::view('/operations/credit-notes', 'operations.resource-list', [
        'resourceType' => 'creditNotes', 'pageTitle' => 'Notas de crédito',
        'pageDescription' => 'Consulta notas de crédito relacionadas con facturas de tu sucursal.',
        'breadcrumbRoot' => 'Devoluciones',
    ])->name('panel.operations.credit-notes');

});
